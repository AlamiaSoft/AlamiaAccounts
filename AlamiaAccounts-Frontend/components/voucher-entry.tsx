"use client"

import type React from "react"

import { useState, useEffect } from "react"
import { Plus, Calendar, FileText } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import VoucherLineItems from "./voucher-line-items"
import VoucherSummary from "./voucher-summary"
import { useVouchers } from "@/hooks/use-vouchers"
import { useToast } from "@/hooks/use-toast"
import { Loader2 } from "lucide-react"
import type { Voucher } from "@/lib/sample-data"

interface LineItem {
  id: string
  account: string
  accountName: string
  debit: number
  credit: number
  description: string
}

interface VoucherEntryProps {
  selectedVoucher?: Voucher | null
  onClearSelection?: () => void
  defaultVoucherType?: string
}

export default function VoucherEntry({ selectedVoucher, onClearSelection, defaultVoucherType }: VoucherEntryProps) {
  const getInitialVoucherType = () => {
    if (defaultVoucherType) {
      return defaultVoucherType
    }
    return "general"
  }

  const getPrefix = (type: string) => {
    switch (type) {
      case "contra": return "CV"
      case "payment": return "PV"
      case "receipt": return "RV"
      case "journal": return "JV"
      case "sales": return "SV"
      case "purchase": return "PUV"
      default: return "V"
    }
  }

  const [voucherType, setVoucherType] = useState<string>(getInitialVoucherType())
  const [voucherNumber, setVoucherNumber] = useState<string>(() => {
    const p = getPrefix(getInitialVoucherType())
    const yr = new Date().getFullYear()
    return `${p}-${yr}-001`
  })
  const [date, setDate] = useState<string>(new Date().toISOString().split("T")[0])
  const [referenceNumber, setReferenceNumber] = useState<string>("")
  const [narration, setNarration] = useState<string>("")
  const [currency, setCurrency] = useState<string>("PKR")
  const [lineItems, setLineItems] = useState<LineItem[]>([
    {
      id: "1",
      account: "1110",
      accountName: "Cash",
      debit: 0,
      credit: 0,
      description: "",
    },
    {
      id: "2",
      account: "2100",
      accountName: "Accounts Payable",
      debit: 0,
      credit: 0,
      description: "",
    },
  ])
  const [isEditingExisting, setIsEditingExisting] = useState(false)

  useEffect(() => {
    if (defaultVoucherType && !selectedVoucher) {
      setVoucherType(defaultVoucherType)
      const p = getPrefix(defaultVoucherType)
      const yr = new Date().getFullYear()
      setVoucherNumber(`${p}-${yr}-${String(Math.floor(100 + Math.random() * 900))}`)
    }
  }, [defaultVoucherType, selectedVoucher])

  useEffect(() => {
    if (selectedVoucher) {
      setVoucherType(selectedVoucher.type || selectedVoucher.voucher_type || "journal")
      setVoucherNumber(selectedVoucher.number || selectedVoucher.reference)
      setDate(selectedVoucher.date)
      setReferenceNumber(selectedVoucher.reference)
      setNarration(selectedVoucher.narration || selectedVoucher.description)
      setLineItems(selectedVoucher.lineItems || selectedVoucher.line_items || [])
      setIsEditingExisting(true)
    }
  }, [selectedVoucher])

  const addLineItem = () => {
    const newItem: LineItem = {
      id: Date.now().toString(),
      account: "",
      accountName: "",
      debit: 0,
      credit: 0,
      description: "",
    }
    setLineItems([...lineItems, newItem])
  }

  const removeLineItem = (id: string) => {
    if (lineItems.length > 2) {
      setLineItems(lineItems.filter((item) => item.id !== id))
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
        // Need credit
        if (last.debit === 0 && last.credit === 0) {
          items[items.length - 1] = { ...last, credit: diff }
        } else {
          items.push({
            id: Date.now().toString(),
            account: "",
            accountName: "",
            debit: 0,
            credit: diff,
            description: "Balancing entry",
          })
        }
      } else {
        // Need debit
        const needed = Math.abs(diff)
        if (last.debit === 0 && last.credit === 0) {
          items[items.length - 1] = { ...last, debit: needed }
        } else {
          items.push({
            id: Date.now().toString(),
            account: "",
            accountName: "",
            debit: needed,
            credit: 0,
            description: "Balancing entry",
          })
        }
      }
      return items
    })
  }

  const totalDebit = lineItems.reduce((sum, item) => sum + (item.debit || 0), 0)
  const totalCredit = lineItems.reduce((sum, item) => sum + (item.credit || 0), 0)
  const isBalanced = totalDebit === totalCredit && totalDebit > 0

  const { createVoucher, updateVoucher } = useVouchers()
  const { toast } = useToast()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!isBalanced) {
      toast({
        title: "Unbalanced Voucher",
        description: "Debits and Credits must be equal.",
        variant: "destructive"
      })
      return
    }

    const payload = {
      date,
      voucher_type: voucherType,
      reference: referenceNumber || voucherNumber,
      description: narration,
      currency,
      entries: lineItems.map(item => ({
        account_code: item.account,
        amount: item.debit > 0 ? item.debit : item.credit,
        type: item.debit > 0 ? 'debit' : 'credit',
        description: item.description
      }))
    }

    try {
      if (isEditingExisting) {
        await updateVoucher.mutateAsync({ reference: selectedVoucher?.reference || voucherNumber, data: payload })
        toast({ title: "Voucher Updated", description: "Successfully updated the voucher." })
      } else {
        await createVoucher.mutateAsync(payload)
        toast({ title: "Voucher Posted", description: "Successfully created and posted the voucher." })
        handleCreateNew()
      }
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.response?.data?.message || "Failed to save voucher.",
        variant: "destructive"
      })
    }
  }

  const handleCreateNew = () => {
    setVoucherType(defaultVoucherType || "general")
    setVoucherNumber("V-2025-001")
    setDate(new Date().toISOString().split("T")[0])
    setReferenceNumber("")
    setNarration("")
    setLineItems([
      {
        id: "1",
        account: "1110",
        accountName: "Cash",
        debit: 0,
        credit: 0,
        description: "",
      },
      {
        id: "2",
        account: "2100",
        accountName: "Accounts Payable",
        debit: 0,
        credit: 0,
        description: "",
      },
    ])
    setIsEditingExisting(false)
    onClearSelection?.()
  }

  const getVoucherTypeDisplay = () => {
    const typeMap: Record<string, string> = {
      general: "General Voucher",
      payment: "Payment Voucher",
      receipt: "Receipt Voucher",
      journal: "Journal Voucher",
      contra: "Contra Voucher",
      sales: "Sales Voucher",
      purchase: "Purchase Voucher",
    }
    return typeMap[voucherType] || "General Voucher"
  }

  return (
    <div className="w-full max-w-6xl mx-auto p-4 md:p-6 lg:p-8">
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <FileText className="w-8 h-8 text-primary" />
              <h1 className="text-3xl font-bold text-foreground">
                {isEditingExisting ? `Edit Voucher: ${voucherNumber}` : `Create ${getVoucherTypeDisplay()}`}
              </h1>
            </div>
            <p className="text-muted-foreground">
              {isEditingExisting
                ? "Modify voucher details and line items"
                : "Record financial transactions with balanced debits and credits"}
            </p>
          </div>
          {isEditingExisting && (
            <Button onClick={handleCreateNew} variant="outline">
              <Plus className="w-4 h-4 mr-2" />
              Create New
            </Button>
          )}
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Header Information */}
        <Card className="border-border">
          <CardHeader className="pb-4">
            <CardTitle className="text-lg">Voucher Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground">Voucher Type</label>
                <Select value={voucherType} onValueChange={setVoucherType}>
                  <SelectTrigger className="bg-input border-input text-foreground">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="general">General Voucher</SelectItem>
                    <SelectItem value="payment">Payment Voucher</SelectItem>
                    <SelectItem value="receipt">Receipt Voucher</SelectItem>
                    <SelectItem value="journal">Journal Voucher</SelectItem>
                    <SelectItem value="contra">Contra Voucher</SelectItem>
                    <SelectItem value="sales">Sales Voucher</SelectItem>
                    <SelectItem value="purchase">Purchase Voucher</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground">Voucher Number</label>
                <Input
                  value={voucherNumber}
                  onChange={(e) => setVoucherNumber(e.target.value)}
                  placeholder="V-2025-001"
                  className="bg-input border-input text-foreground placeholder:text-muted-foreground"
                  disabled
                />
              </div>

              <div className="space-y-2">
                <label htmlFor="date" className="text-sm font-medium text-foreground flex items-center gap-2">
                  <Calendar className="w-4 h-4" />
                  Date
                </label>
                <Input
                  id="date"
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  className="bg-input border-input text-foreground"
                />
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground">Reference</label>
                <Input
                  value={referenceNumber}
                  onChange={(e) => setReferenceNumber(e.target.value)}
                  placeholder="Invoice / PO Number"
                  className="bg-input border-input text-foreground placeholder:text-muted-foreground"
                />
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground">Currency</label>
                <Select value={currency} onValueChange={setCurrency}>
                  <SelectTrigger className="bg-input border-input text-foreground">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="PKR">PKR - Pakistan Rupee</SelectItem>
                    <SelectItem value="USD">USD - US Dollar</SelectItem>
                    <SelectItem value="GBP">GBP - British Pound</SelectItem>
                    <SelectItem value="EUR">EUR - Euro</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium text-foreground">Narration</label>
              <textarea
                value={narration}
                onChange={(e) => setNarration(e.target.value)}
                placeholder="Enter transaction description..."
                className="w-full px-3 py-2 bg-input border border-input rounded-md text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                rows={2}
              />
            </div>
          </CardContent>
        </Card>

        {/* Line Items */}
        <Card className="border-border">
          <CardHeader className="pb-4">
            <div className="flex items-center justify-between">
              <CardTitle className="text-lg">Accounts & Amounts</CardTitle>
            </div>
            <CardDescription>Add debit and credit entries to balance the voucher</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <VoucherLineItems lineItems={lineItems} onUpdate={updateLineItem} onRemove={removeLineItem} />

            <Button
              type="button"
              onClick={addLineItem}
              variant="outline"
              className="w-full border-dashed border-2 border-primary text-primary hover:bg-primary/5 bg-transparent"
            >
              <Plus className="w-4 h-4 mr-2" />
              Add Line Item
            </Button>
          </CardContent>
        </Card>

        {/* Summary */}
        <VoucherSummary
          totalDebit={totalDebit}
          totalCredit={totalCredit}
          isBalanced={isBalanced}
          onAutoBalance={handleAutoBalance}
        />

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-3 justify-end">
          <Button
            type="button"
            variant="outline"
            className="border-border text-foreground hover:bg-muted bg-transparent"
          >
            Save as Draft
          </Button>
          <Button
            type="submit"
            disabled={!isBalanced || createVoucher.isPending || updateVoucher.isPending}
            className="bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
          >
            {createVoucher.isPending || updateVoucher.isPending ? (
              <Loader2 className="w-4 h-4 mr-2 animate-spin" />
            ) : null}
            {isEditingExisting ? "Update Voucher" : "Post Voucher"}
          </Button>
        </div>
      </form>
    </div>
  )
}
