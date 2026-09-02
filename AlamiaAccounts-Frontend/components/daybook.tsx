"use client"

import { useState, useMemo } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Download, Printer, Receipt, Loader2, Calendar, ArrowLeftRight } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { useVouchers } from "@/hooks/use-vouchers"
import { useAccounts } from "@/hooks/use-accounts"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { voucherApi } from "@/lib/api"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { cn } from "@/lib/utils"

interface FlattenedEntry {
  id: string
  voucherNo: string
  voucherType: string
  date: string
  accountName: string
  debit: number
  credit: number
  narration: string
}

export default function DayBook() {
  const queryClient = useQueryClient()
  const [selectedDate, setSelectedDate] = useState(() => new Date().toISOString().split("T")[0])
  const [showAllDates, setShowAllDates] = useState(false)
  const [reversalTarget, setReversalTarget] = useState<string | null>(null)
  const [reversalReason, setReversalReason] = useState("")
  const [statusAlert, setStatusAlert] = useState<{ type: "success" | "error"; message: string } | null>(null)

  const { vouchers: apiVouchers, isLoading } = useVouchers()
  const { accounts: allAccounts } = useAccounts()

  const reverseMutation = useMutation({
    mutationFn: ({ ref, reason }: { ref: string; reason: string }) =>
      voucherApi.reverse(ref, { reason }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["vouchers"] })
      queryClient.invalidateQueries({ queryKey: ["reports"] })
      setStatusAlert({
        type: "success",
        message: `Voucher ${reversalTarget} reversed successfully. Compensating entry REV-${reversalTarget} posted to ledger.`,
      })
      setReversalTarget(null)
      setReversalReason("")
      setTimeout(() => setStatusAlert(null), 6000)
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Failed to reverse voucher"
      setStatusAlert({ type: "error", message: msg })
    },
  })

  // Set of reversed voucher references
  const reversedRefs = useMemo(() => {
    const set = new Set<string>()
    for (const v of apiVouchers || []) {
      const ref = String(v.reference || v.number || "")
      if (ref.toUpperCase().startsWith("REV-")) {
        set.add(ref.substring(4).toUpperCase())
      }
    }
    return set
  }, [apiVouchers])

  // Map of account code to name for rich display
  const accountMap = useMemo(() => {
    const map = new Map<string, string>()
    for (const a of allAccounts || []) {
      const name = a.name || (a.names && a.names[0]?.name) || ""
      if (name && a.code) {
        map.set(a.code, name)
      }
    }
    return map
  }, [allAccounts])

  // Flatten vouchers and line items for the daybook
  const { entries, totalDebit, totalCredit } = useMemo(() => {
    const list: FlattenedEntry[] = []
    let drSum = 0
    let crSum = 0

    const rawVouchers = apiVouchers || []
    const filteredVouchers = showAllDates
      ? rawVouchers
      : rawVouchers.filter((v: any) => v.date?.startsWith(selectedDate))

    for (const v of filteredVouchers) {
      const items = v.line_items || v.details || []
      const vNo = v.reference || v.number || `VCH-${v.id}`
      const vNarration = v.description || v.narration || ""
      let vType = v.type || v.voucher_type || "Journal"
      if (vNo.toUpperCase().startsWith("CV") || vNo.toUpperCase().startsWith("CONTRA")) {
        vType = "Contra"
      } else if (vNo.toUpperCase().startsWith("OB")) {
        vType = "Opening Balance"
      }

      if (items.length > 0) {
        items.forEach((item: any, idx: number) => {
          const debit = Number(item.debit) || 0
          const credit = Number(item.credit) || 0
          drSum += debit
          crSum += credit

          const code = String(item.account_code || item.account || "")
          let name = item.raw_name || item.account_name || ""
          // If name is missing, equal to code, or purely numeric, resolve from accountMap
          if (!name || name === code || /^\d+$/.test(name.trim())) {
            name = accountMap.get(code) || name || code
          }
          const cleanName = name.replace(new RegExp(`\\s*\\(${code}\\)$`), "").trim()
          const displayAccount = cleanName && code && cleanName !== code
            ? `${cleanName} (${code})`
            : cleanName || code || "Unknown Account"

          list.push({
            id: `${v.id || vNo}-${idx}`,
            voucherNo: vNo,
            voucherType: vType,
            date: v.date,
            accountName: displayAccount,
            debit,
            credit,
            narration: item.description || item.memo || vNarration,
          })
        })
      } else {
        // In case voucher has flat amount
        const amt = Number(v.amount) || 0
        drSum += amt
        crSum += amt
        list.push({
          id: String(v.id || vNo),
          voucherNo: vNo,
          voucherType: vType,
          date: v.date,
          accountName: v.account_name || "General Transaction",
          debit: amt,
          credit: 0,
          narration: vNarration,
        })
      }
    }

    return { entries: list, totalDebit: drSum, totalCredit: crSum }
  }, [apiVouchers, selectedDate, showAllDates])

  const handlePrint = () => {
    window.print()
  }

  const handleExportCSV = () => {
    const headers = ["Voucher No", "Type", "Date", "Account", "Debit", "Credit", "Narration"]
    const rows = entries.map((e) => [
      e.voucherNo,
      e.voucherType,
      e.date,
      `"${e.accountName.replace(/"/g, '""')}"`,
      e.debit,
      e.credit,
      `"${e.narration.replace(/"/g, '""')}"`,
    ])
    const csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map((r) => r.join(","))].join("\n")
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute("download", `DayBook_${selectedDate}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center flex-wrap gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Day Book</h2>
          <p className="text-muted-foreground mt-1">
            Chronological register of all financial transactions and journal vouchers posted
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={handleExportCSV} disabled={entries.length === 0}>
            <Download className="w-4 h-4 mr-2" />
            Export CSV
          </Button>
          <Button variant="outline" onClick={handlePrint}>
            <Printer className="w-4 h-4 mr-2" />
            Print
          </Button>
        </div>
      </div>

      {/* Date Filter & Options */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-center gap-4 flex-wrap">
            <div className="w-64">
              <label className="text-sm font-medium mb-1 block">Select Date</label>
              <Input
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
                disabled={showAllDates}
              />
            </div>

            <div className="pt-6">
              <Button
                variant={showAllDates ? "default" : "outline"}
                onClick={() => setShowAllDates(!showAllDates)}
              >
                {showAllDates ? "Filtering by Single Date" : "Show All Recent Dates"}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Debits</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600">
              Rs. {totalDebit.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Credits</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">
              Rs. {totalCredit.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card className={totalDebit === totalCredit ? "bg-green-500/5 border-green-500/20" : "bg-red-500/5 border-red-500/20"}>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Balance Check</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {totalDebit === totalCredit ? (
                <span className="text-green-600 text-lg flex items-center">
                  Balanced (Diff: Rs. 0) ✓
                </span>
              ) : (
                <span className="text-red-600 text-lg">
                  Out of Balance (Diff: Rs. {Math.abs(totalDebit - totalCredit).toLocaleString()})
                </span>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {statusAlert && (
        <Alert variant={statusAlert.type === "error" ? "destructive" : "default"} className={statusAlert.type === "success" ? "border-green-500 bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-200" : ""}>
          <AlertDescription className="font-medium">{statusAlert.message}</AlertDescription>
        </Alert>
      )}

      {/* Entries Table */}
      <Card>
        <CardHeader>
          <CardTitle>
            {showAllDates ? "All Recent Vouchers" : `Vouchers on ${selectedDate}`}
          </CardTitle>
          <CardDescription>
            Detailed line items for double-entry transactions
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-primary" />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Voucher No</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Account Name</TableHead>
                    <TableHead className="text-right">Debit</TableHead>
                    <TableHead className="text-right">Credit</TableHead>
                    <TableHead>Narration</TableHead>
                    <TableHead className="text-right">Action</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {entries.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={8} className="text-center py-12 text-muted-foreground">
                        No vouchers recorded for this period.
                      </TableCell>
                    </TableRow>
                  ) : (
                    entries.map((entry) => (
                      <TableRow key={entry.id}>
                        <TableCell className="font-mono font-medium text-xs">
                          {entry.voucherNo}
                        </TableCell>
                        <TableCell>
                          <Badge
                            variant="outline"
                            className={cn(
                              "capitalize font-medium text-xs flex items-center gap-1 w-fit",
                              entry.voucherType.toLowerCase().includes("contra") && "bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-300",
                              entry.voucherType.toLowerCase().includes("opening") && "bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-300",
                              entry.voucherType.toLowerCase().includes("receipt") && "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-300",
                              entry.voucherType.toLowerCase().includes("payment") && "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-300"
                            )}
                          >
                            {entry.voucherType.toLowerCase().includes("contra") && <ArrowLeftRight className="w-3 h-3" />}
                            {entry.voucherType}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-mono text-xs text-muted-foreground">
                          {entry.date}
                        </TableCell>
                        <TableCell className="font-medium">{entry.accountName}</TableCell>
                        <TableCell className="text-right font-medium text-green-600">
                          {entry.debit > 0 ? `Rs. ${entry.debit.toLocaleString()}` : "-"}
                        </TableCell>
                        <TableCell className="text-right font-medium text-blue-600">
                          {entry.credit > 0 ? `Rs. ${entry.credit.toLocaleString()}` : "-"}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-xs max-w-xs truncate">
                          {entry.narration || "-"}
                        </TableCell>
                        <TableCell className="text-right">
                          {entry.voucherNo.toUpperCase().startsWith("REV-") ? (
                            <Badge variant="secondary" className="text-xs">Reversal</Badge>
                          ) : reversedRefs.has(entry.voucherNo.toUpperCase()) ? (
                            <Badge variant="destructive" className="text-xs">Reversed</Badge>
                          ) : (
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 text-xs text-destructive hover:bg-destructive/10"
                              onClick={() => {
                                setReversalTarget(entry.voucherNo)
                                setReversalReason("")
                              }}
                            >
                              Reverse
                            </Button>
                          )}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Reversal Confirmation Dialog */}
      <Dialog open={!!reversalTarget} onOpenChange={(open) => !open && setReversalTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reverse Voucher: {reversalTarget}</DialogTitle>
            <DialogDescription>
              Posting a reversal will generate an inverse compensating entry to neutralize this transaction while preserving the complete audit history.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <Label htmlFor="reversal-reason">Business Reason for Reversal *</Label>
            <Textarea
              id="reversal-reason"
              placeholder="Provide a documented reason (e.g., Billing correction, incorrect amount, double charge)..."
              value={reversalReason}
              onChange={(e) => setReversalReason(e.target.value)}
              className="min-h-[80px]"
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setReversalTarget(null)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              disabled={!reversalReason.trim() || reverseMutation.isPending}
              onClick={() => {
                if (reversalTarget && reversalReason.trim()) {
                  reverseMutation.mutate({ ref: reversalTarget, reason: reversalReason.trim() })
                }
              }}
            >
              {reverseMutation.isPending ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : null}
              Confirm Reversal
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
