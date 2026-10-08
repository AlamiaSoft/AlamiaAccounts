"use client"

import React, { useState, useEffect, useMemo } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Checkbox } from "@/components/ui/checkbox"
import { Badge } from "@/components/ui/badge"
import { Calendar, Save, CheckCircle2, AlertCircle, ArrowLeft, Printer, RefreshCw, FileText } from "lucide-react"
import VoucherLineItems from "./voucher-line-items"
import VoucherSummary from "./voucher-summary"
import { useVouchers } from "@/hooks/use-vouchers"
import { useVoucherTypes } from "@/hooks/use-voucher-types"
import { useAccounts } from "@/hooks/use-accounts"
import { useToast } from "@/hooks/use-toast"
import { isAccountInGroup } from "@/lib/account-rules-helper"
import type { Company } from "./company-switcher"

interface LineItem {
  id: string
  account: string
  accountName: string
  debit: number
  credit: number
  description: string
}

interface CustomVoucherEntryProps {
  voucherTypeId?: string | number
  voucherPrefix?: string
  currentCompany: Company
  onBack?: () => void
  onSuccessNavigate?: (voucher: any) => void
}

export default function CustomVoucherEntry({
  voucherTypeId,
  voucherPrefix,
  currentCompany,
  onBack,
  onSuccessNavigate,
}: CustomVoucherEntryProps) {
  const { voucherTypes, isLoading: isLoadingTypes } = useVoucherTypes(currentCompany?.code)
  const { accounts } = useAccounts()
  const { createVoucher } = useVouchers()
  const { toast } = useToast()

  // Find the matching custom voucher type
  const voucherType = useMemo(() => {
    if (!voucherTypes || voucherTypes.length === 0) return null
    if (voucherTypeId) {
      return voucherTypes.find((vt: any) => String(vt.id) === String(voucherTypeId)) || voucherTypes[0]
    }
    if (voucherPrefix) {
      return (
        voucherTypes.find((vt: any) => vt.prefix?.toLowerCase() === voucherPrefix.toLowerCase()) || voucherTypes[0]
      )
    }
    return voucherTypes[0]
  }, [voucherTypes, voucherTypeId, voucherPrefix])

  // Dynamic custom field values
  const [customFieldValues, setCustomFieldValues] = useState<Record<string, any>>({})

  // Standard voucher header states
  const [date, setDate] = useState<string>(new Date().toISOString().split("T")[0])
  const [referenceNumber, setReferenceNumber] = useState<string>("")
  const [narration, setNarration] = useState<string>("")
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false)

  // Double entry lines
  const [lineItems, setLineItems] = useState<LineItem[]>([
    {
      id: "1",
      account: "",
      accountName: "",
      debit: 0,
      credit: 0,
      description: "",
    },
    {
      id: "2",
      account: "",
      accountName: "",
      debit: 0,
      credit: 0,
      description: "",
    },
  ])

  // Initialize reference number and defaults when voucher type is loaded
  useEffect(() => {
    if (voucherType) {
      const p = voucherType.prefix || "CST"
      const yr = new Date().getFullYear()
      const rand = Math.floor(100 + Math.random() * 900)
      const num = `${p}-${yr}-${String(rand).padStart(4, "0")}`
      setReferenceNumber(num)

      // Set dynamically configured default debit & credit accounts from the Custom Voucher Type account rules
      const rules = voucherType.account_rules || voucherType.accountRules || []
      const debitRule = rules.find((r: any) => r.side === "debit")
      const creditRule = rules.find((r: any) => r.side === "credit")

      const defaultDebit = debitRule?.default_account || debitRule?.defaultAccount || ""
      const defaultCredit = creditRule?.default_account || creditRule?.defaultAccount || ""
      
      const debitAcc = defaultDebit ? accounts?.find((a: any) => String(a.code) === String(defaultDebit)) : null
      const creditAcc = defaultCredit ? accounts?.find((a: any) => String(a.code) === String(defaultCredit)) : null

      const debitAccName = debitAcc?.name || ""
      const creditAccName = creditAcc?.name || ""

      setLineItems([
        {
          id: "1",
          account: defaultDebit,
          accountName: debitAccName,
          debit: 0,
          credit: 0,
          description: "",
        },
        {
          id: "2",
          account: defaultCredit,
          accountName: creditAccName,
          debit: 0,
          credit: 0,
          description: "",
        },
      ])

      // Initialize default custom field values
      const initialFields: Record<string, any> = {}
      if (Array.isArray(voucherType.custom_fields)) {
        voucherType.custom_fields.forEach((f: any) => {
          if (f.type === "dropdown" && Array.isArray(f.options) && f.options.length > 0) {
            initialFields[f.name] = f.options[0]
          } else if (f.type === "checkbox") {
            initialFields[f.name] = false
          } else {
            initialFields[f.name] = ""
          }
        })
      }
      setCustomFieldValues(initialFields)
    }
  }, [voucherType, accounts])

  const totalDebit = useMemo(() => {
    return lineItems.reduce((sum, item) => sum + (Number(item.debit) || 0), 0)
  }, [lineItems])

  const totalCredit = useMemo(() => {
    return lineItems.reduce((sum, item) => sum + (Number(item.credit) || 0), 0)
  }, [lineItems])

  const isBalanced = Math.abs(totalDebit - totalCredit) < 0.001 && totalDebit > 0

  // Helper to insert commission lines with pre-filled amounts for explicit accountant selection
  const handleAddCommissionLines = (commAmount: number) => {
    if (commAmount <= 0) return

    setLineItems((prev) => [
      ...prev,
      {
        id: (Date.now() + 1).toString(),
        account: "",
        accountName: "",
        debit: commAmount,
        credit: 0,
        description: `Agent Commission Expense - ${customFieldValues["Passenger Name"] || referenceNumber}`,
      },
      {
        id: (Date.now() + 2).toString(),
        account: "",
        accountName: "",
        debit: 0,
        credit: commAmount,
        description: `Commission Payable to Agent - ${customFieldValues["Passenger Name"] || referenceNumber}`,
      },
    ])
    toast({
      title: "Commission Lines Added",
      description: `Added Debit & Credit line items for Rs. ${commAmount.toLocaleString()}. Please select the Commission Expense and Agent Payable accounts from the dropdowns.`,
    })
  }

  const handleCustomFieldChange = (fieldName: string, value: any) => {
    setCustomFieldValues((prev) => {
      const updated = { ...prev, [fieldName]: value }

      // Auto-populate gross fare to debit/credit if user changes Gross Fare field
      if (fieldName.toLowerCase().includes("fare") || fieldName.toLowerCase().includes("amount")) {
        const numVal = parseFloat(value) || 0
        if (numVal > 0 && lineItems.length >= 2) {
          setLineItems((prevLines) => [
            { ...prevLines[0], debit: numVal, credit: 0 },
            { ...prevLines[1], debit: 0, credit: numVal },
            ...prevLines.slice(2),
          ])
        }
      }

      // Auto-update narration if passenger name / PNR changes
      if (fieldName === "Passenger Name" || fieldName === "PNR" || fieldName === "Sector Route") {
        const pass = updated["Passenger Name"] || ""
        const pnr = updated["PNR"] || ""
        const sector = updated["Sector Route"] || ""
        if (pass || pnr || sector) {
          setNarration(`Ticket Booking: ${pass}${pnr ? ` (PNR: ${pnr})` : ""}${sector ? ` Sector: ${sector}` : ""}`)
        }
      }

      return updated
    })
  }

  const addLineItem = () => {
    setLineItems((prev) => [
      ...prev,
      {
        id: Date.now().toString(),
        account: "",
        accountName: "",
        debit: 0,
        credit: 0,
        description: "",
      },
    ])
  }

  const removeLineItem = (id: string) => {
    if (lineItems.length > 2) {
      setLineItems((prev) => prev.filter((item) => item.id !== id))
    }
  }

  const updateLineItem = (id: string, fieldOrUpdates: keyof LineItem | Partial<LineItem>, value?: any) => {
    setLineItems((prev) =>
      prev.map((item) => {
        if (item.id !== id) return item
        if (typeof fieldOrUpdates === "string") {
          return { ...item, [fieldOrUpdates]: value }
        }
        return { ...item, ...fieldOrUpdates }
      })
    )
  }

  const handleAutoBalance = () => {
    const diff = totalDebit - totalCredit
    if (diff === 0) return

    setLineItems((prev) => {
      const items = [...prev]
      const last = items[items.length - 1]
      if (diff > 0) {
        if (last.debit === 0 && last.credit === 0) {
          items[items.length - 1] = { ...last, credit: diff }
        } else {
          items.push({
            id: Date.now().toString(),
            account: "",
            accountName: "",
            debit: 0,
            credit: diff,
            description: "",
          })
        }
      } else {
        if (last.debit === 0 && last.credit === 0) {
          items[items.length - 1] = { ...last, debit: Math.abs(diff) }
        } else {
          items.push({
            id: Date.now().toString(),
            account: "",
            accountName: "",
            debit: Math.abs(diff),
            credit: 0,
            description: "",
          })
        }
      }
      return items
    })
  }

  const handleSubmit = async () => {
    if (!voucherType) return

    // 1. Validate custom required fields
    if (Array.isArray(voucherType.custom_fields)) {
      for (const f of voucherType.custom_fields) {
        if (f.required && (!customFieldValues[f.name] || String(customFieldValues[f.name]).trim() === "")) {
          toast({
            title: "Validation Error",
            description: `Field "${f.name}" is required.`,
            variant: "destructive",
          })
          return
        }
      }
    }

    // 2. Validate double entry balance
    if (!isBalanced) {
      toast({
        title: "Double-Entry Balance Error",
        description: `Debits (Rs. ${totalDebit.toLocaleString()}) must exactly equal Credits (Rs. ${totalCredit.toLocaleString()}). Difference: Rs. ${Math.abs(totalDebit - totalCredit).toLocaleString()}`,
        variant: "destructive",
      })
      return
    }

    // 3. Validate accounts selected
    const invalidLine = lineItems.find((l) => !l.account || (l.debit === 0 && l.credit === 0))
    if (invalidLine) {
      toast({
        title: "Invalid Line Items",
        description: "All line items must have a valid account code and an amount.",
        variant: "destructive",
      })
      return
    }

    // 4. Validate accounts against custom voucher type account rules
    const rules = voucherType.account_rules || voucherType.accountRules || []
    if (rules.length > 0) {
      for (let i = 0; i < lineItems.length; i++) {
        const item = lineItems[i]
        const side: "debit" | "credit" =
          Number(item.debit) > 0 ? "debit" : Number(item.credit) > 0 ? "credit" : i === 0 ? "debit" : "credit"
        const sideRules = rules.filter((r: any) => r.side === side)
        
        if (sideRules.length > 0) {
          const allowedGroups: string[] = []
          sideRules.forEach((r: any) => {
            const groups = Array.isArray(r.account_groups)
              ? r.account_groups
              : Array.isArray(r.accountGroups)
              ? r.accountGroups
              : typeof r.account_groups === "string"
              ? JSON.parse(r.account_groups || "[]")
              : []
            groups.forEach((g: string) => {
              if (g && !allowedGroups.includes(g)) allowedGroups.push(g)
            })
          })

          if (allowedGroups.length > 0) {
            const accObj = accounts?.find((a: any) => String(a.code).toLowerCase() === item.account.trim().toLowerCase())
            const isAllowed = accObj && allowedGroups.some((g) => isAccountInGroup(accObj, g))
            if (!isAllowed) {
              toast({
                title: "Account Rule Violation",
                description: `Line ${i + 1} (${side.toUpperCase()} [${item.account}] ${item.accountName}) is not permitted. Only accounts in [${allowedGroups.join(", ")}] are allowed.`,
                variant: "destructive",
              })
              return
            }
          }
        }
      }
    }

    setIsSubmitting(true)
    try {
      const voucherPayload = {
        number: referenceNumber,
        reference: referenceNumber,
        type: voucherType.prefix?.toLowerCase() || "custom",
        voucher_type: voucherType.name,
        date: date,
        currency: currentCompany?.currency || "PKR",
        narration: narration || `${voucherType.name} - ${referenceNumber}`,
        description: narration || `${voucherType.name} - ${referenceNumber}`,
        custom_fields: customFieldValues,
        entries: lineItems.map((item) => {
          const debitAmt = Number(item.debit) || 0
          const creditAmt = Number(item.credit) || 0
          return {
            account_code: item.account,
            amount: debitAmt > 0 ? debitAmt : creditAmt,
            type: debitAmt > 0 ? "debit" : "credit",
            description: item.description || narration || `${voucherType.name} - ${referenceNumber}`,
          }
        }),
        lineItems: lineItems.map((item) => ({
          account: item.account,
          account_code: item.account,
          accountName: item.accountName,
          account_name: item.accountName,
          debit: Number(item.debit) || 0,
          credit: Number(item.credit) || 0,
          description: item.description || narration,
        })),
        company_code: currentCompany?.code || "MAIN",
      }

      await createVoucher.mutateAsync(voucherPayload)

      toast({
        title: "Voucher Posted Successfully! ✈️",
        description: `${voucherType.name} ${referenceNumber} (Rs. ${totalDebit.toLocaleString()}) posted to general ledger.`,
      })

      if (onSuccessNavigate) {
        onSuccessNavigate(voucherPayload)
      } else if (onBack) {
        onBack()
      }
    } catch (err: any) {
      console.error("Voucher posting error:", err)
      toast({
        title: "Posting Failed",
        description: err?.response?.data?.message || err?.message || "Failed to post custom voucher.",
        variant: "destructive",
      })
    } finally {
      setIsSubmitting(false)
    }
  }

  if (isLoadingTypes) {
    return (
      <div className="p-8 text-center text-muted-foreground">
        <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2" />
        Loading Custom Voucher Type...
      </div>
    )
  }

  if (!voucherType) {
    return (
      <Card className="p-6 text-center space-y-4">
        <AlertCircle className="w-12 h-12 text-amber-500 mx-auto" />
        <CardTitle>No Custom Voucher Type Found</CardTitle>
        <CardDescription>
          No custom voucher type was found matching your selection for {currentCompany?.name}.
        </CardDescription>
        {onBack && (
          <Button variant="outline" onClick={onBack}>
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Dashboard
          </Button>
        )}
      </Card>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          {onBack && (
            <Button variant="ghost" size="icon" onClick={onBack}>
              <ArrowLeft className="w-5 h-5" />
            </Button>
          )}
          <div>
            <div className="flex items-center gap-2.5">
              <h2 className="text-3xl font-bold">{voucherType.name}</h2>
              <Badge variant="outline" className="font-mono text-sm px-2.5 py-0.5 border-primary/40 text-primary">
                {voucherType.prefix}
              </Badge>
              <Badge variant="secondary" className="text-xs">
                {currentCompany?.name}
              </Badge>
            </div>
            <p className="text-muted-foreground mt-1">{voucherType.description || "Custom Voucher Entry Screen"}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => window.print()}>
            <Printer className="w-4 h-4 mr-2" />
            Print Form
          </Button>
          <Button onClick={handleSubmit} disabled={isSubmitting || !isBalanced} className="min-w-[140px]">
            {isSubmitting ? (
              <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
            ) : (
              <Save className="w-4 h-4 mr-2" />
            )}
            Post Voucher
          </Button>
        </div>
      </div>

      {/* Main Voucher Card */}
      <Card>
        <CardHeader className="bg-muted/30 border-b pb-4">
          <CardTitle className="text-lg flex items-center justify-between">
            <span>Voucher Header & General Info</span>
            <span className="text-xs font-normal text-muted-foreground">Tenant: {currentCompany?.code}</span>
          </CardTitle>
        </CardHeader>
        <CardContent className="pt-6 space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="space-y-2">
              <Label>Voucher Reference #</Label>
              <Input
                value={referenceNumber}
                onChange={(e) => setReferenceNumber(e.target.value)}
                className="font-mono font-semibold"
                placeholder="TKT-2026-0001"
              />
            </div>
            <div className="space-y-2">
              <Label>Date</Label>
              <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>Currency</Label>
              <Input value="PKR (Pakistani Rupee)" disabled className="bg-muted" />
            </div>
          </div>

          {/* Dynamic Custom Fields Section */}
          {Array.isArray(voucherType.custom_fields) && voucherType.custom_fields.length > 0 && (
            <div className="p-4 border rounded-lg bg-card space-y-4">
              <div className="flex items-center gap-2 border-b pb-2">
                <FileText className="w-4 h-4 text-primary" />
                <h4 className="font-semibold text-sm uppercase tracking-wide">
                  Custom {voucherType.name} Dynamic Fields
                </h4>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {voucherType.custom_fields.map((field: any, idx: number) => {
                  const val = customFieldValues[field.name] ?? ""
                  return (
                    <div key={idx} className="space-y-1.5">
                      <Label className="text-sm font-medium">
                        {field.name}
                        {field.required && <span className="text-destructive ml-1">*</span>}
                      </Label>
                      {field.type === "dropdown" ? (
                        <Select
                          value={val || (field.options?.[0] ?? "")}
                          onValueChange={(v) => handleCustomFieldChange(field.name, v)}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder={`Select ${field.name}`} />
                          </SelectTrigger>
                          <SelectContent>
                            {Array.isArray(field.options) &&
                              field.options.map((opt: string) => (
                                <SelectItem key={opt} value={opt}>
                                  {opt}
                                </SelectItem>
                              ))}
                          </SelectContent>
                        </Select>
                      ) : field.type === "checkbox" ? (
                        <div className="flex items-center gap-2 pt-2">
                          <Checkbox
                            checked={Boolean(val)}
                            onCheckedChange={(checked) => handleCustomFieldChange(field.name, checked)}
                          />
                          <span className="text-sm">{field.name}</span>
                        </div>
                      ) : field.type === "number" ? (
                        <Input
                          type="number"
                          placeholder={`Enter ${field.name}`}
                          value={val}
                          onChange={(e) => handleCustomFieldChange(field.name, e.target.value)}
                        />
                      ) : (
                        <Input
                          type="text"
                          placeholder={`Enter ${field.name}`}
                          value={val}
                          onChange={(e) => handleCustomFieldChange(field.name, e.target.value)}
                        />
                      )}
                    </div>
                  )
                })}
              </div>
            </div>
          )}

          {/* Narration */}
          <div className="space-y-2">
            <Label>Narration / Description</Label>
            <Textarea
              rows={2}
              placeholder="Enter voucher description, remarks or details"
              value={narration}
              onChange={(e) => setNarration(e.target.value)}
            />
          </div>
        </CardContent>
      </Card>

      {/* Double-Entry Ledger Lines */}
      <Card>
        <CardHeader className="bg-muted/30 border-b pb-4">
          <CardTitle className="text-lg">Accounting Ledger Line Items</CardTitle>
          <CardDescription>
            Specify balanced double-entry accounting postings for this {voucherType.name}
          </CardDescription>
        </CardHeader>
        <CardContent className="pt-6 space-y-4">
          {Number(customFieldValues["Agent Commission"] || customFieldValues["Commission"] || 0) > 0 && (
            <div className="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg text-xs">
              <span className="text-amber-900 dark:text-amber-200 font-medium">
                💡 Agent Commission detected: <strong>Rs. {Number(customFieldValues["Agent Commission"] || customFieldValues["Commission"]).toLocaleString()}</strong>
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => handleAddCommissionLines(Number(customFieldValues["Agent Commission"] || customFieldValues["Commission"]))}
                className="h-7 text-xs border-amber-300 text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-900/50"
              >
                + Add Commission Accounting Lines (Dr/Cr)
              </Button>
            </div>
          )}

          <VoucherLineItems
            lineItems={lineItems}
            onUpdate={updateLineItem}
            onRemove={removeLineItem}
            onAddLineItem={addLineItem}
            onRemoveLineItem={removeLineItem}
            onUpdateLineItem={updateLineItem}
            currency="PKR"
            companyCode={currentCompany?.code}
            accountRules={voucherType.account_rules || voucherType.accountRules || []}
          />

          <VoucherSummary
            totalDebit={totalDebit}
            totalCredit={totalCredit}
            currency="PKR"
            isBalanced={isBalanced}
            onAutoBalance={handleAutoBalance}
          />
        </CardContent>
      </Card>
    </div>
  )
}
