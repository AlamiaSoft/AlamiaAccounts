"use client"

import React, { useMemo } from "react"
import { Trash2, Check, AlertTriangle } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { useAccounts } from "@/hooks/use-accounts"
import AccountCombobox, { type AccountOption } from "./account-combobox"

export interface LineItem {
  id: string
  account: string
  accountName: string
  debit: number
  credit: number
  description: string
}

interface VoucherLineItemsProps {
  lineItems: LineItem[]
  onUpdate: (id: string, fieldOrUpdates: keyof LineItem | Partial<LineItem>, value?: any) => void
  onRemove: (id: string) => void
  disabled?: boolean
}

export default function VoucherLineItems({
  lineItems,
  onUpdate,
  onRemove,
  disabled = false,
}: VoucherLineItemsProps) {
  const { accounts, isLoading: isLoadingAccounts } = useAccounts()

  const accountList: AccountOption[] = useMemo(() => {
    return (accounts || []).map((acc: any) => ({
      code: String(acc.code),
      name: String(acc.name),
      category: Boolean(acc.category),
      type: acc.type,
      groupId: acc.groupId,
    }))
  }, [accounts])

  // Helper map for fast lookup by code
  const accountsByCode = useMemo(() => {
    const map = new Map<string, AccountOption>()
    accountList.forEach((acc) => {
      map.set(acc.code.toLowerCase(), acc)
    })
    return map
  }, [accountList])

  const handleCodeChange = (id: string, rawCode: string) => {
    const code = rawCode.trim()
    const matched = accountsByCode.get(code.toLowerCase())

    if (matched) {
      if (matched.category) {
        // Folder account cannot be posted to
        onUpdate(id, {
          account: rawCode,
          accountName: `${matched.name} (Folder - Non-posting)`,
        })
      } else {
        onUpdate(id, {
          account: matched.code,
          accountName: matched.name,
        })
      }
    } else {
      // Freeform or in-progress code typing
      onUpdate(id, {
        account: rawCode,
        accountName: "",
      })
    }
  }

  const handleAccountSelect = (id: string, selected: { code: string; name: string }) => {
    onUpdate(id, {
      account: selected.code,
      accountName: selected.name,
    })
  }

  const handleDebitChange = (id: string, rawVal: string) => {
    const val = rawVal ? Number.parseFloat(rawVal) : 0
    // If entering debit, clear credit
    if (val > 0) {
      onUpdate(id, { debit: val, credit: 0 })
    } else {
      onUpdate(id, "debit", 0)
    }
  }

  const handleCreditChange = (id: string, rawVal: string) => {
    const val = rawVal ? Number.parseFloat(rawVal) : 0
    // If entering credit, clear debit
    if (val > 0) {
      onUpdate(id, { credit: val, debit: 0 })
    } else {
      onUpdate(id, "credit", 0)
    }
  }

  return (
    <div className="overflow-x-auto min-h-[360px] pb-32">
      <table className="w-full border-collapse">
        <thead>
          <tr className="border-b border-border bg-muted/20">
            <th className="text-left py-3 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground w-36">
              Account Code
            </th>
            <th className="text-left py-3 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground min-w-[280px]">
              Account Name (Searchable)
            </th>
            <th className="text-right py-3 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground w-36">
              Debit (PKR)
            </th>
            <th className="text-right py-3 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground w-36">
              Credit (PKR)
            </th>
            <th className="text-left py-3 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground min-w-[180px]">
              Description
            </th>
            <th className="text-center py-3 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground w-16">
              Action
            </th>
          </tr>
        </thead>
        <tbody>
          {lineItems.map((item, index) => {
            const matchedAccount = accountsByCode.get(item.account.trim().toLowerCase())
            const isCategory = matchedAccount?.category
            const isValidPosting = matchedAccount && !matchedAccount.category

            return (
              <tr
                key={item.id}
                style={{ zIndex: (lineItems.length - index) * 10 }}
                className="border-b border-border/70 hover:bg-muted/30 transition-colors relative"
              >
                {/* 1. Account Code with Live Validation */}
                <td className="py-2.5 px-3 align-top">
                  <div className="relative">
                    <Input
                      value={item.account}
                      onChange={(e) => handleCodeChange(item.id, e.target.value)}
                      placeholder="e.g. 1110"
                      className={`h-9 font-mono text-xs sm:text-sm pr-7 bg-background ${
                        isCategory
                          ? "border-amber-500 focus-visible:ring-amber-500"
                          : isValidPosting
                          ? "border-emerald-500/50"
                          : ""
                      }`}
                      disabled={disabled}
                    />
                    <div className="absolute right-2 top-2.5 pointer-events-none">
                      {isValidPosting && (
                        <Check className="w-4 h-4 text-emerald-600" />
                      )}
                      {isCategory && (
                        <AlertTriangle
                          className="w-4 h-4 text-amber-500"
                        />
                      )}
                    </div>
                  </div>
                  {isCategory && (
                    <p className="text-[10px] text-amber-600 font-medium mt-1 leading-tight">
                      Folder: select sub-account
                    </p>
                  )}
                </td>

                {/* 2. Searchable Account Name Combobox */}
                <td className="py-2.5 px-3 align-top">
                  <AccountCombobox
                    accounts={accountList}
                    selectedCode={item.account}
                    selectedName={item.accountName}
                    onSelect={(selected) => handleAccountSelect(item.id, selected)}
                    disabled={disabled}
                    placeholder="Select or search account by name..."
                  />
                </td>

                {/* 3. Debit Input */}
                <td className="py-2.5 px-3 align-top">
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={item.debit || ""}
                    onChange={(e) => handleDebitChange(item.id, e.target.value)}
                    placeholder="0.00"
                    className="h-9 text-right font-mono text-sm bg-background"
                    disabled={disabled}
                  />
                </td>

                {/* 4. Credit Input */}
                <td className="py-2.5 px-3 align-top">
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={item.credit || ""}
                    onChange={(e) => handleCreditChange(item.id, e.target.value)}
                    placeholder="0.00"
                    className="h-9 text-right font-mono text-sm bg-background"
                    disabled={disabled}
                  />
                </td>

                {/* 5. Description Input */}
                <td className="py-2.5 px-3 align-top">
                  <Input
                    value={item.description}
                    onChange={(e) => onUpdate(item.id, "description", e.target.value)}
                    placeholder="Optional line note"
                    className="h-9 text-sm bg-background"
                    disabled={disabled}
                  />
                </td>

                {/* 6. Action: Delete Line */}
                <td className="py-2.5 px-3 text-center align-top">
                  <Button
                    type="button"
                    onClick={() => onRemove(item.id)}
                    variant="ghost"
                    size="sm"
                    className="h-9 w-9 p-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                    disabled={lineItems.length <= 2 || disabled}
                    title="Remove Line"
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}