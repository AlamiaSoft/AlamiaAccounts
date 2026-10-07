"use client"

import { useState, useMemo } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Download, Printer, Receipt, Loader2, Calendar, ArrowLeftRight, Trash2 } from "lucide-react"
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
import { useSales } from "@/hooks/use-sales"
import PosSalesApproval from "@/components/pos-sales-approval"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { ShoppingBag, BookOpen } from "lucide-react"

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
  const [activeTab, setActiveTab] = useState<"daybook" | "pos-approvals">("daybook")
  const [reversalTarget, setReversalTarget] = useState<string | null>(null)
  const [reversalReason, setReversalReason] = useState("")
  const [isClearDialogOpen, setIsClearDialogOpen] = useState(false)
  const [statusAlert, setStatusAlert] = useState<{ type: "success" | "error"; message: string } | null>(null)

  const { vouchers: apiVouchers, isLoading } = useVouchers()
  const { accounts: allAccounts } = useAccounts()
  const { sales } = useSales({ status: "staged" })
  const pendingSalesCount = useMemo(() => (sales || []).filter((s: any) => s.status === "staged").length, [sales])

  const clearMutation = useMutation({
    mutationFn: () => voucherApi.clearAll(),
    onSuccess: (res: any) => {
      queryClient.invalidateQueries({ queryKey: ["vouchers"] })
      queryClient.invalidateQueries({ queryKey: ["accounts"] })
      queryClient.invalidateQueries({ queryKey: ["reports"] })
      queryClient.invalidateQueries({ queryKey: ["audit-trail"] })
      const count = res?.data?.cleared_count ?? 0
      setStatusAlert({
        type: "success",
        message: `Successfully cleared ${count} transactions from the ledger. Chart of Accounts and settings have been preserved.`,
      })
      setIsClearDialogOpen(false)
      setTimeout(() => setStatusAlert(null), 6000)
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Failed to clear transactions"
      setStatusAlert({ type: "error", message: msg })
    },
  })

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
      } else if (vNo.toUpperCase().startsWith("TKT")) {
        vType = "Airline Ticket"
      } else if (vNo.toUpperCase().startsWith("SF")) {
        vType = "School Fees"
      } else if (vNo.toUpperCase().startsWith("PV")) {
        vType = "Payment"
      } else if (vNo.toUpperCase().startsWith("RV")) {
        vType = "Receipt"
      } else if (vNo.toUpperCase().startsWith("SV")) {
        vType = "Sales"
      } else if (vNo.toUpperCase().startsWith("PUV")) {
        vType = "Purchase"
      } else if (vNo.toUpperCase().startsWith("JV")) {
        vType = "Journal"
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
      {/* Header & Tabs */}
      <div className="flex justify-between items-center flex-wrap gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Day Book & Transaction Register</h2>
          <p className="text-muted-foreground mt-1">
            Chronological register of all financial transactions, journal vouchers, and front-desk staged approvals.
          </p>
        </div>
        <div className="flex gap-2">
          {activeTab === "daybook" && (
            <>
              <Button
                variant="outline"
                onClick={() => setIsClearDialogOpen(true)}
                className="border-destructive/40 text-destructive hover:bg-destructive/10"
                disabled={entries.length === 0 && (apiVouchers || []).length === 0}
              >
                <Trash2 className="w-4 h-4 mr-2 text-destructive" />
                Clear All Transactions
              </Button>
              <Button variant="outline" onClick={handleExportCSV} disabled={entries.length === 0}>
                <Download className="w-4 h-4 mr-2" />
                Export CSV
              </Button>
              <Button variant="outline" onClick={handlePrint}>
                <Printer className="w-4 h-4 mr-2" />
                Print
              </Button>
            </>
          )}
        </div>
      </div>

      {/* Mode Switcher Tabs */}
      <div className="flex items-center justify-between border-b pb-4">
        <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full sm:w-auto">
          <TabsList className="grid grid-cols-2 w-full sm:w-[420px]">
            <TabsTrigger value="daybook" className="flex items-center gap-2">
              <BookOpen className="w-4 h-4" />
              Posted Journal Day Book
            </TabsTrigger>
            <TabsTrigger value="pos-approvals" className="flex items-center gap-2 relative">
              <ShoppingBag className="w-4 h-4" />
              POS Staged Approvals
              {pendingSalesCount > 0 && (
                <span className="ml-1.5 px-1.5 py-0.2 text-[10px] font-bold rounded-full bg-amber-500 text-white animate-pulse">
                  {pendingSalesCount}
                </span>
              )}
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      {activeTab === "pos-approvals" ? (
        <PosSalesApproval />
      ) : (
        <>

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
                              entry.voucherType.toLowerCase().includes("payment") && "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-300",
                              entry.voucherType.toLowerCase().includes("ticket") && "bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 border-sky-300",
                              entry.voucherType.toLowerCase().includes("school") && "bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-300",
                              entry.voucherType.toLowerCase().includes("sales") && "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-300",
                              entry.voucherType.toLowerCase().includes("purchase") && "bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300 border-orange-300"
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
      {/* Clear All Transactions Confirmation Dialog */}
      <Dialog open={isClearDialogOpen} onOpenChange={setIsClearDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="text-destructive flex items-center gap-2">
              <Trash2 className="w-5 h-5 text-destructive" />
              Clear All Transactions & Vouchers
            </DialogTitle>
            <DialogDescription>
              This action will reset and remove all posted journal vouchers and transaction entries for the current company, returning ledger balances to zero (Rs. 0.00).
            </DialogDescription>
          </DialogHeader>
          <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg text-xs text-amber-900 dark:text-amber-300 space-y-1">
            <strong>What will be preserved:</strong>
            <ul className="list-disc pl-4 space-y-0.5 mt-1">
              <li>Chart of Accounts structure & hierarchy</li>
              <li>Custom Voucher Types & visual builder templates</li>
              <li>Company profile, accounting periods, and user permissions</li>
            </ul>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsClearDialogOpen(false)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              disabled={clearMutation.isPending}
              onClick={() => clearMutation.mutate()}
            >
              {clearMutation.isPending ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Trash2 className="w-4 h-4 mr-2" />}
              Yes, Clear All Transactions
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
        </>
      )}
    </div>
  )
}
