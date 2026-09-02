"use client"

import { Trash2 } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"

import { useAccounts } from "@/hooks/use-accounts"

interface LineItem {
  id: string
  account: string
  accountName: string
  debit: number
  credit: number
  description: string
}

interface VoucherLineItemsProps {
  lineItems: LineItem[]
  onUpdate: (id: string, field: keyof LineItem, value: any) => void
  onRemove: (id: string) => void
  disabled?: boolean // Added disabled prop to support view mode
}

export default function VoucherLineItems({ lineItems, onUpdate, onRemove, disabled = false }: VoucherLineItemsProps) {
  const { accounts } = useAccounts()
  const accountList = (accounts || []).map((acc: any) => ({
    code: acc.code,
    name: acc.name
  }))

  return (
    <div className="overflow-x-auto">
      <table className="w-full">
        <thead>
          <tr className="border-b border-border">
            <th className="text-left py-3 px-3 text-sm font-semibold text-foreground">Account Code</th>
            <th className="text-left py-3 px-3 text-sm font-semibold text-foreground">Account Name</th>
            <th className="text-right py-3 px-3 text-sm font-semibold text-foreground">Debit</th>
            <th className="text-right py-3 px-3 text-sm font-semibold text-foreground">Credit</th>
            <th className="text-left py-3 px-3 text-sm font-semibold text-foreground">Description</th>
            <th className="text-center py-3 px-3 text-sm font-semibold text-foreground">Action</th>
          </tr>
        </thead>
        <tbody>
          {lineItems.map((item, index) => (
            <tr key={item.id} className="border-b border-border hover:bg-muted/50 transition-colors">
              <td className="py-3 px-3">
                <Input
                  value={item.account}
                  onChange={(e) => {
                    const account = accountList.find((a) => a.code === e.target.value)
                    onUpdate(item.id, "account", e.target.value)
                    if (account) {
                      onUpdate(item.id, "accountName", account.name)
                    }
                  }}
                  placeholder="e.g., 1001"
                  className="bg-input border-input text-foreground text-sm"
                  list={`accounts-${item.id}`}
                  disabled={disabled}
                />
                <datalist id={`accounts-${item.id}`}>
                  {accountList.map((acc) => (
                    <option key={acc.code} value={acc.code}>
                      {acc.name}
                    </option>
                  ))}
                </datalist>
              </td>
              <td className="py-3 px-3">
                <div className="text-sm text-foreground font-medium">{item.accountName || "-"}</div>
              </td>
              <td className="py-3 px-3">
                <Input
                  type="number"
                  min="0"
                  step="0.01"
                  value={item.debit || ""}
                  onChange={(e) => onUpdate(item.id, "debit", e.target.value ? Number.parseFloat(e.target.value) : 0)}
                  placeholder="0.00"
                  className="bg-input border-input text-foreground text-sm text-right"
                  disabled={disabled}
                />
              </td>
              <td className="py-3 px-3">
                <Input
                  type="number"
                  min="0"
                  step="0.01"
                  value={item.credit || ""}
                  onChange={(e) => onUpdate(item.id, "credit", e.target.value ? Number.parseFloat(e.target.value) : 0)}
                  placeholder="0.00"
                  className="bg-input border-input text-foreground text-sm text-right"
                  disabled={disabled}
                />
              </td>
              <td className="py-3 px-3">
                <Input
                  value={item.description}
                  onChange={(e) => onUpdate(item.id, "description", e.target.value)}
                  placeholder="Optional note"
                  className="bg-input border-input text-foreground text-sm"
                  disabled={disabled}
                />
              </td>
              <td className="py-3 px-3 text-center">
                <Button
                  type="button"
                  onClick={() => onRemove(item.id)}
                  variant="ghost"
                  size="sm"
                  className="h-8 w-8 p-0 text-destructive hover:bg-destructive/10"
                  disabled={lineItems.length <= 2 || disabled}
                >
                  <Trash2 className="w-4 h-4" />
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
