"use client"

import { useState, useMemo } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import {
  CheckCircle2,
  Clock,
  Printer,
  Search,
  ExternalLink,
  ShoppingBag,
  RefreshCw,
  Loader2,
  AlertCircle,
  FileText,
  DollarSign,
  UserCheck,
  Calculator,
  ShieldCheck,
  ArrowRight
} from "lucide-react"
import { useSales, useShiftReconciliation } from "@/hooks/use-sales"

export default function PosSalesApproval() {
  const [searchTerm, setSearchTerm] = useState("")
  const [statusFilter, setStatusFilter] = useState<string>("staged")
  const [selectedSale, setSelectedSale] = useState<any | null>(null)
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [isShiftModalOpen, setIsShiftModalOpen] = useState(false)
  const [statusAlert, setStatusAlert] = useState<{ type: "success" | "error"; message: string } | null>(null)

  // Shift Closure Denomination State
  const [shiftDate, setShiftDate] = useState(() => new Date().toISOString().split("T")[0])
  const [shiftAgent, setShiftAgent] = useState<string>("")
  const [cashCounts, setCashCounts] = useState<{ [key: string]: number }>({
    5000: 0,
    1000: 0,
    500: 0,
    100: 0,
    50: 0,
    20: 0,
    10: 0,
    coins: 0,
  })
  const [shiftNotes, setShiftNotes] = useState("")

  const totalPhysicalCash = useMemo(() => {
    return (
      (cashCounts[5000] || 0) * 5000 +
      (cashCounts[1000] || 0) * 1000 +
      (cashCounts[500] || 0) * 500 +
      (cashCounts[100] || 0) * 100 +
      (cashCounts[50] || 0) * 50 +
      (cashCounts[20] || 0) * 20 +
      (cashCounts[10] || 0) * 10 +
      (cashCounts.coins || 0)
    )
  }, [cashCounts])

  const { data: shiftData, isLoading: isLoadingShift, refetch: refetchShift } = useShiftReconciliation({
    date: shiftDate,
    agent_id: shiftAgent || undefined,
    actual_cash: totalPhysicalCash,
  })

  const { sales, isLoading, refetch, approveSale } = useSales({
    status: statusFilter === "all" ? undefined : statusFilter,
    role: "manager",
  })

  const filteredSales = useMemo(() => {
    if (!searchTerm.trim()) return sales
    const term = searchTerm.toLowerCase()
    return sales.filter((s: any) => {
      const cust = s.customer_name?.toLowerCase() || ""
      const ref = s.client_reference_id?.toLowerCase() || ""
      const agent = s.agent_name?.toLowerCase() || ""
      const passport = s.customer_passport?.toLowerCase() || ""
      const sv = s.sales_voucher_ref?.toLowerCase() || ""
      const rv = s.receipt_voucher_ref?.toLowerCase() || ""
      return (
        cust.includes(term) ||
        ref.includes(term) ||
        agent.includes(term) ||
        passport.includes(term) ||
        sv.includes(term) ||
        rv.includes(term)
      )
    })
  }, [sales, searchTerm])

  // Summary Metrics
  const stats = useMemo(() => {
    let pendingCount = 0
    let pendingAmount = 0
    let pendingAdvance = 0
    let postedCount = 0

    for (const s of sales) {
      const amt = Number(s.total_amount ?? s.net_amount ?? s.gross_amount) || 0
      const paid = Number(s.paid_amount) || 0
      if (s.status === "staged") {
        pendingCount++
        pendingAmount += amt
        pendingAdvance += paid
      } else if (s.status === "posted") {
        postedCount++
      }
    }
    return { pendingCount, pendingAmount, pendingAdvance, postedCount }
  }, [sales])

  const handleApprove = async (sale: any) => {
    try {
      const res = await approveSale.mutateAsync({
        id: sale.id,
        approverName: "Head Accountant (Manager)",
      })
      setStatusAlert({
        type: "success",
        message: `Sale #${sale.id} (${sale.client_reference_id || "POS"}) successfully approved! Created Sales Voucher ${res.data?.sales_voucher_ref || "SV"} and Receipt Voucher ${res.data?.receipt_voucher_ref || "RV"}.`,
      })
      if (isDetailModalOpen) {
        setIsDetailModalOpen(false)
        setSelectedSale(null)
      }
      setTimeout(() => setStatusAlert(null), 8000)
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message || "Failed to approve sale."
      setStatusAlert({ type: "error", message: msg })
    }
  }

  const handlePrintReceipt = (id: number | string) => {
    const rawBase = (process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api").trim()
    const apiBase = rawBase.replace(/\/+api\/?$/i, "").replace(/\/+$/, "")
    window.open(`${apiBase}/api/v1/receipts/${id}/print`, "_blank", "width=850,height=900")
  }

  return (
    <div className="space-y-6">
      {/* Top Banner Header */}
      <div className="flex justify-between items-center flex-wrap gap-4">
        <div>
          <div className="flex items-center gap-3">
            <h2 className="text-3xl font-bold tracking-tight">Front-Office POS & Sales Approvals</h2>
            <Badge variant="outline" className="text-xs bg-amber-500/10 text-amber-600 border-amber-500/30">
              Audit & Ledger Gate
            </Badge>
          </div>
          <p className="text-muted-foreground mt-1">
            Review front-desk travel ticketing, visa bookings, and POS counter transactions staged for general ledger posting.
          </p>
        </div>
        <div className="flex gap-2 flex-wrap">
          <Button
            variant="outline"
            onClick={() => setIsShiftModalOpen(true)}
            className="border-amber-500/50 hover:bg-amber-500/10 text-amber-700 dark:text-amber-400 font-medium"
          >
            <Calculator className="w-4 h-4 mr-2 text-amber-600" />
            Shift Closure & Handover
          </Button>
          <Button
            variant="outline"
            onClick={() => window.open("/ke-pos.html", "_blank")}
            className="border-primary/40 hover:bg-primary/5 text-primary"
          >
            <ShoppingBag className="w-4 h-4 mr-2" />
            Open Front-Office POS
            <ExternalLink className="w-3.5 h-3.5 ml-1 opacity-70" />
          </Button>
          <Button variant="outline" onClick={() => refetch()} disabled={isLoading}>
            <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Notification banner */}
      {statusAlert && (
        <Alert
          className={
            statusAlert.type === "success"
              ? "bg-emerald-50 border-emerald-300 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-700 dark:text-emerald-200"
              : "bg-destructive/10 border-destructive text-destructive"
          }
        >
          {statusAlert.type === "success" ? (
            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          ) : (
            <AlertCircle className="h-4 w-4" />
          )}
          <AlertTitle>{statusAlert.type === "success" ? "Posting Approved" : "Action Blocked"}</AlertTitle>
          <AlertDescription>{statusAlert.message}</AlertDescription>
        </Alert>
      )}

      {/* Summary KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="border-l-4 border-l-amber-500">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center justify-between">
              Pending Approvals
              <Clock className="w-4 h-4 text-amber-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">
              {stats.pendingCount} <span className="text-xs font-normal text-muted-foreground">orders staged</span>
            </div>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-blue-500">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center justify-between">
              Total Staged Volume
              <DollarSign className="w-4 h-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
              Rs. {stats.pendingAmount.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-emerald-500">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center justify-between">
              Staged Cash Collected
              <UserCheck className="w-4 h-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
              Rs. {stats.pendingAdvance.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-purple-500">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center justify-between">
              Posted & Committed
              <CheckCircle2 className="w-4 h-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
              {stats.postedCount} <span className="text-xs font-normal text-muted-foreground">vouchers live</span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filter and Search Bar */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
            <div className="flex gap-2 flex-wrap w-full md:w-auto">
              <Button
                variant={statusFilter === "staged" ? "default" : "outline"}
                size="sm"
                onClick={() => setStatusFilter("staged")}
                className={statusFilter === "staged" ? "bg-amber-600 hover:bg-amber-700" : ""}
              >
                Pending Approvals ({stats.pendingCount})
              </Button>
              <Button
                variant={statusFilter === "posted" ? "default" : "outline"}
                size="sm"
                onClick={() => setStatusFilter("posted")}
              >
                Posted to Ledger ({stats.postedCount})
              </Button>
              <Button
                variant={statusFilter === "all" ? "default" : "outline"}
                size="sm"
                onClick={() => setStatusFilter("all")}
              >
                All Transactions
              </Button>
            </div>

            <div className="relative w-full md:w-80">
              <Search className="w-4 h-4 absolute left-3 top-3 text-muted-foreground" />
              <Input
                placeholder="Search by customer, passport, ref..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-9"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Main Transactions Table */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle>Staged POS Sales Register</CardTitle>
          <CardDescription>
            {statusFilter === "staged"
              ? "All sales in 'staged' mode require a manager review before journal vouchers (SV/RV) are committed to the General Ledger."
              : "Historical ledger postings and front-office checkout records."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center justify-center py-16 text-muted-foreground">
              <Loader2 className="w-6 h-6 animate-spin mr-2" />
              Loading staged sales from server...
            </div>
          ) : filteredSales.length === 0 ? (
            <div className="text-center py-16 text-muted-foreground">
              <ShoppingBag className="w-12 h-12 mx-auto mb-3 opacity-30" />
              <p className="font-medium">No sales records found</p>
              <p className="text-sm mt-1">
                {statusFilter === "staged"
                  ? "All front-office counter sales have been approved and committed to the ledger!"
                  : "No sales match the selected filters."}
              </p>
              <Button
                variant="outline"
                size="sm"
                className="mt-4"
                onClick={() => window.open("/ke-pos.html", "_blank")}
              >
                Create new sale in POS
              </Button>
            </div>
          ) : (
            <div className="rounded-md border overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-[120px]">Ref / Date</TableHead>
                    <TableHead>Customer / Passport</TableHead>
                    <TableHead>Service / Items</TableHead>
                    <TableHead>Agent / Counter</TableHead>
                    <TableHead className="text-right">Total (PKR)</TableHead>
                    <TableHead className="text-right">Paid (PKR)</TableHead>
                    <TableHead className="text-center">Status / Vouchers</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredSales.map((sale: any) => {
                    const isStaged = sale.status === "staged"
                    const isPosted = sale.status === "posted"
                    const createdDate = sale.created_at ? new Date(sale.created_at).toLocaleDateString() : "-"
                    const totalAmt = Number(sale.total_amount ?? sale.net_amount ?? sale.gross_amount) || 0
                    const paidAmt = Number(sale.paid_amount) || 0
                    const balanceDue = totalAmt - paidAmt

                    return (
                      <TableRow key={sale.id} className={isStaged ? "bg-amber-50/30 dark:bg-amber-950/10" : ""}>
                        <TableCell>
                          <div className="font-semibold text-xs text-primary">
                            {sale.client_reference_id || `SALE-${sale.id}`}
                          </div>
                          <div className="text-xs text-muted-foreground">{createdDate}</div>
                        </TableCell>
                        <TableCell>
                          <div className="font-medium text-sm">{sale.customer_name || "Walk-in Client"}</div>
                          {sale.customer_passport && (
                            <div className="text-xs text-muted-foreground font-mono">
                              P.No: {sale.customer_passport}
                            </div>
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="text-xs max-w-xs truncate" title={sale.items?.[0]?.description}>
                            {sale.items?.[0]?.description || "General Travel Service"}
                          </div>
                          {sale.items?.length > 1 && (
                            <Badge variant="secondary" className="text-[10px] py-0 px-1 mt-0.5">
                              +{sale.items.length - 1} more items
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="text-xs font-medium">{sale.agent_name || "Counter Staff"}</div>
                          <div className="text-[10px] text-muted-foreground">ID: {sale.agent_id || "counter_01"}</div>
                        </TableCell>
                        <TableCell className="text-right font-bold text-sm">
                          Rs. {totalAmt.toLocaleString()}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="font-semibold text-xs text-emerald-600 dark:text-emerald-400">
                            Rs. {paidAmt.toLocaleString()}
                          </div>
                          {balanceDue > 0 && (
                            <div className="text-[10px] text-red-500 font-medium">
                              Due: Rs. {balanceDue.toLocaleString()}
                            </div>
                          )}
                        </TableCell>
                        <TableCell className="text-center">
                          {isStaged ? (
                            <Badge variant="outline" className="bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300 text-xs">
                              <Clock className="w-3 h-3 mr-1" /> Pending Approval
                            </Badge>
                          ) : isPosted ? (
                            <div className="space-y-1">
                              <Badge variant="outline" className="bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 text-xs">
                                <CheckCircle2 className="w-3 h-3 mr-1" /> Posted
                              </Badge>
                              {sale.sales_voucher_ref && (
                                <div className="text-[10px] font-mono text-muted-foreground">
                                  {sale.sales_voucher_ref}
                                  {sale.receipt_voucher_ref ? ` / ${sale.receipt_voucher_ref}` : ""}
                                </div>
                              )}
                            </div>
                          ) : (
                            <Badge variant="secondary" className="text-xs">
                              {sale.status}
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {isStaged && (
                              <Button
                                size="sm"
                                variant="default"
                                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-8 px-3"
                                onClick={() => handleApprove(sale)}
                                disabled={approveSale.isPending}
                              >
                                {approveSale.isPending ? (
                                  <Loader2 className="w-3.5 h-3.5 animate-spin mr-1" />
                                ) : (
                                  <CheckCircle2 className="w-3.5 h-3.5 mr-1" />
                                )}
                                Approve & Post
                              </Button>
                            )}

                            <Button
                              size="sm"
                              variant="outline"
                              className="h-8 px-2 text-xs"
                              onClick={() => {
                                setSelectedSale(sale)
                                setIsDetailModalOpen(true)
                              }}
                            >
                              <FileText className="w-3.5 h-3.5 mr-1" />
                              Details
                            </Button>

                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0"
                              title="Print Receipt"
                              onClick={() => handlePrintReceipt(sale.id)}
                            >
                              <Printer className="w-3.5 h-3.5" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Sale Detail & Accounting Breakdown Modal */}
      <Dialog open={isDetailModalOpen} onOpenChange={setIsDetailModalOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="flex items-center justify-between">
              <span>Sale #{selectedSale?.id} — {selectedSale?.client_reference_id}</span>
              <Badge variant={selectedSale?.status === "staged" ? "outline" : "default"}>
                {selectedSale?.status?.toUpperCase()}
              </Badge>
            </DialogTitle>
            <DialogDescription>
              Front-office operational breakdown and double-entry ledger impact.
            </DialogDescription>
          </DialogHeader>

          {selectedSale && (
            <div className="space-y-4 py-2 text-sm">
              {/* Customer & Agent Details */}
              <div className="grid grid-cols-2 gap-4 p-3 bg-muted/40 rounded-lg">
                <div>
                  <div className="text-xs text-muted-foreground uppercase font-semibold">Customer / Client</div>
                  <div className="font-semibold text-base">{selectedSale.customer_name || "Walk-in"}</div>
                  {selectedSale.customer_passport && (
                    <div className="text-xs text-muted-foreground">Passport: {selectedSale.customer_passport}</div>
                  )}
                  <div className="text-xs text-muted-foreground">Subledger Code: {selectedSale.customer_subledger || "1200"}</div>
                </div>
                <div>
                  <div className="text-xs text-muted-foreground uppercase font-semibold">Counter Staff / Cashier</div>
                  <div className="font-semibold text-base">{selectedSale.agent_name || "Counter Agent"}</div>
                  <div className="text-xs text-muted-foreground">Mode: {selectedSale.workflow_mode || "staged_approval"}</div>
                  <div className="text-xs text-muted-foreground">Date: {new Date(selectedSale.created_at).toLocaleString()}</div>
                </div>
              </div>

              {/* Items Breakdown */}
              <div>
                <h4 className="font-semibold text-xs text-muted-foreground uppercase mb-2">Order Line Items</h4>
                <div className="border rounded-md overflow-hidden">
                  <Table>
                    <TableHeader>
                      <TableRow className="text-xs">
                        <TableHead>Description</TableHead>
                        <TableHead className="text-center">Qty</TableHead>
                        <TableHead className="text-right">Price</TableHead>
                        <TableHead className="text-right">Account</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {selectedSale.items?.map((item: any, i: number) => (
                        <TableRow key={i} className="text-xs">
                          <TableCell>
                            <div className="font-medium">{item.description}</div>
                            {item.metadata?.pnr && (
                              <div className="text-[10px] text-muted-foreground">
                                PNR: {item.metadata.pnr} • Sector: {item.metadata.route}
                              </div>
                            )}
                          </TableCell>
                          <TableCell className="text-center">{item.quantity || 1}</TableCell>
                          <TableCell className="text-right font-semibold">
                            Rs. {Number(item.unit_price).toLocaleString()}
                          </TableCell>
                          <TableCell className="text-right font-mono text-[11px] text-muted-foreground">
                            {item.revenue_account_code || "3100"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </div>

              {/* Double Entry Breakdown */}
              <div className="p-3 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900 rounded-lg">
                <h4 className="font-semibold text-xs text-blue-900 dark:text-blue-200 uppercase mb-2">
                  Ledger Posting Specification
                </h4>
                <div className="text-xs space-y-1">
                  <div className="flex justify-between">
                    <span>1. Sales Voucher (SV): Dr. Accounts Receivable ({selectedSale.customer_subledger || "1200"})</span>
                    <span className="font-mono font-semibold">Rs. {Number(selectedSale.total_amount ?? selectedSale.net_amount ?? selectedSale.gross_amount).toLocaleString()}</span>
                  </div>
                  <div className="flex justify-between pl-4 text-muted-foreground">
                    <span>Cr. Revenue / Sales ({selectedSale.items?.[0]?.revenue_account_code || "3100"})</span>
                    <span className="font-mono">Rs. {Number(selectedSale.total_amount ?? selectedSale.net_amount ?? selectedSale.gross_amount).toLocaleString()}</span>
                  </div>
                  {Number(selectedSale.paid_amount) > 0 && (
                    <>
                      <div className="flex justify-between pt-1 border-t border-blue-200 dark:border-blue-900">
                        <span>2. Receipt Voucher (RV): Dr. Cash / Bank ({selectedSale.payment_account || "1110"})</span>
                        <span className="font-mono font-semibold">Rs. {Number(selectedSale.paid_amount).toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between pl-4 text-muted-foreground">
                        <span>Cr. Accounts Receivable ({selectedSale.customer_subledger || "1200"})</span>
                        <span className="font-mono">Rs. {Number(selectedSale.paid_amount).toLocaleString()}</span>
                      </div>
                    </>
                  )}
                </div>
              </div>
            </div>
          )}

          <DialogFooter className="flex justify-between sm:justify-between items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => handlePrintReceipt(selectedSale?.id)}
            >
              <Printer className="w-4 h-4 mr-2" />
              Print Receipt
            </Button>

            <div className="flex gap-2">
              <Button variant="ghost" size="sm" onClick={() => setIsDetailModalOpen(false)}>
                Close
              </Button>
              {selectedSale?.status === "staged" && (
                <Button
                  size="sm"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white"
                  onClick={() => handleApprove(selectedSale)}
                  disabled={approveSale.isPending}
                >
                  {approveSale.isPending ? (
                    <Loader2 className="w-4 h-4 animate-spin mr-2" />
                  ) : (
                    <CheckCircle2 className="w-4 h-4 mr-2" />
                  )}
                  Approve & Post to Ledger
                </Button>
              )}
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Cashier Shift Closure & Handover Modal */}
      <Dialog open={isShiftModalOpen} onOpenChange={setIsShiftModalOpen}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Calculator className="w-5 h-5 text-amber-600" />
                <span>Cashier Shift Closure & End-of-Day Cash Handover</span>
              </div>
              <Badge variant="outline" className="text-xs bg-primary/10 text-primary border-primary/20">
                Cash Drawer Audit
              </Badge>
            </DialogTitle>
            <DialogDescription>
              Count physical currency notes in the counter cash drawer and compare against expected POS receipts in the ledger.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2 text-sm">
            {/* Filter Bar */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-muted/40 rounded-lg">
              <div>
                <label className="text-xs font-semibold text-muted-foreground uppercase block mb-1">Shift Date</label>
                <Input
                  type="date"
                  value={shiftDate}
                  onChange={(e) => setShiftDate(e.target.value)}
                  className="h-8 text-xs"
                />
              </div>
              <div>
                <label className="text-xs font-semibold text-muted-foreground uppercase block mb-1">Cashier / Agent ID (Optional)</label>
                <Input
                  placeholder="e.g. counter_01 or leave blank for ALL"
                  value={shiftAgent}
                  onChange={(e) => setShiftAgent(e.target.value)}
                  className="h-8 text-xs"
                />
              </div>
            </div>

            {/* Reconciliation Comparison Summary */}
            <div className="grid grid-cols-3 gap-3">
              <Card className="bg-slate-50 dark:bg-slate-900 border-slate-200">
                <CardHeader className="p-3 pb-1">
                  <CardTitle className="text-xs text-muted-foreground">Expected System Cash</CardTitle>
                </CardHeader>
                <CardContent className="p-3 pt-0">
                  <div className="text-xl font-bold text-blue-600 dark:text-blue-400">
                    Rs. {(shiftData?.cash_drawer?.expected_cash || 0).toLocaleString()}
                  </div>
                  <div className="text-[10px] text-muted-foreground mt-0.5">
                    From {shiftData?.summary?.total_transactions || 0} transactions
                  </div>
                </CardContent>
              </Card>

              <Card className="bg-slate-50 dark:bg-slate-900 border-slate-200">
                <CardHeader className="p-3 pb-1">
                  <CardTitle className="text-xs text-muted-foreground">Counted Physical Cash</CardTitle>
                </CardHeader>
                <CardContent className="p-3 pt-0">
                  <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                    Rs. {totalPhysicalCash.toLocaleString()}
                  </div>
                  <div className="text-[10px] text-muted-foreground mt-0.5">
                    Denomination total
                  </div>
                </CardContent>
              </Card>

              <Card className={
                Math.abs(totalPhysicalCash - (shiftData?.cash_drawer?.expected_cash || 0)) < 0.01
                  ? "bg-emerald-50/60 dark:bg-emerald-950/20 border-emerald-300"
                  : totalPhysicalCash > (shiftData?.cash_drawer?.expected_cash || 0)
                  ? "bg-blue-50/60 dark:bg-blue-950/20 border-blue-300"
                  : "bg-red-50/60 dark:bg-red-950/20 border-red-300"
              }>
                <CardHeader className="p-3 pb-1">
                  <CardTitle className="text-xs text-muted-foreground">Discrepancy</CardTitle>
                </CardHeader>
                <CardContent className="p-3 pt-0">
                  {(() => {
                    const diff = totalPhysicalCash - (shiftData?.cash_drawer?.expected_cash || 0)
                    const isBalanced = Math.abs(diff) < 0.01
                    return (
                      <>
                        <div className={`text-xl font-bold ${
                          isBalanced
                            ? "text-emerald-600 dark:text-emerald-400"
                            : diff > 0
                            ? "text-blue-600 dark:text-blue-400"
                            : "text-red-600 dark:text-red-400"
                        }`}>
                          {diff >= 0 ? `+Rs. ${diff.toLocaleString()}` : `-Rs. ${Math.abs(diff).toLocaleString()}`}
                        </div>
                        <Badge variant="outline" className={`text-[10px] mt-1 ${
                          isBalanced
                            ? "bg-emerald-100 text-emerald-800 border-emerald-300"
                            : diff > 0
                            ? "bg-blue-100 text-blue-800 border-blue-300"
                            : "bg-red-100 text-red-800 border-red-300"
                        }`}>
                          {isBalanced ? "Balanced ✓" : diff > 0 ? "Surplus Cash" : "Cash Shortage"}
                        </Badge>
                      </>
                    )
                  })()}
                </CardContent>
              </Card>
            </div>

            {/* Note Denomination Counter */}
            <div>
              <h4 className="text-xs font-semibold text-muted-foreground uppercase mb-2">Physical Cash Denomination Counter</h4>
              <div className="border rounded-md p-3 bg-card">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  {[
                    { label: "Rs. 5,000", val: 5000, key: 5000 },
                    { label: "Rs. 1,000", val: 1000, key: 1000 },
                    { label: "Rs. 500", val: 500, key: 500 },
                    { label: "Rs. 100", val: 100, key: 100 },
                    { label: "Rs. 50", val: 50, key: 50 },
                    { label: "Rs. 20", val: 20, key: 20 },
                    { label: "Rs. 10", val: 10, key: 10 },
                    { label: "Coins / Misc", val: 1, key: "coins" },
                  ].map((denom) => {
                    const count = cashCounts[denom.key] || 0
                    const subtotal = count * denom.val
                    return (
                      <div key={denom.key} className="p-2 border rounded bg-muted/20 text-xs space-y-1">
                        <div className="flex justify-between font-semibold">
                          <span>{denom.label}</span>
                          <span className="text-muted-foreground font-mono">Rs. {subtotal.toLocaleString()}</span>
                        </div>
                        <Input
                          type="number"
                          min="0"
                          placeholder="Count"
                          value={cashCounts[denom.key] || ""}
                          onChange={(e) => {
                            const v = Math.max(0, parseInt(e.target.value, 10) || 0)
                            setCashCounts((prev) => ({ ...prev, [denom.key]: v }))
                          }}
                          className="h-7 text-xs font-mono"
                        />
                      </div>
                    )
                  })}
                </div>
              </div>
            </div>

            {/* Shift Notes */}
            <div>
              <label className="text-xs font-semibold text-muted-foreground uppercase block mb-1">Supervisor & Handover Notes</label>
              <Input
                placeholder="Document any handover remarks, petty cash balance, or authorized shortages..."
                value={shiftNotes}
                onChange={(e) => setShiftNotes(e.target.value)}
                className="text-xs"
              />
            </div>
          </div>

          <DialogFooter className="flex justify-between sm:justify-between items-center gap-2 border-t pt-3">
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                const diff = totalPhysicalCash - (shiftData?.cash_drawer?.expected_cash || 0)
                const isBalanced = Math.abs(diff) < 0.01
                const printWindow = window.open("", "_blank", "width=750,height=850")
                if (printWindow) {
                  printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                      <title>Shift Handover Slip - ${shiftDate}</title>
                      <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 25px; color: #111; max-width: 650px; margin: 0 auto; }
                        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 15px; }
                        .header h2 { margin: 0; font-size: 20px; text-transform: uppercase; }
                        .header p { margin: 4px 0 0 0; font-size: 12px; color: #555; }
                        .meta-table, .denom-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px; }
                        .meta-table td { padding: 4px 0; }
                        .denom-table th, .denom-table td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
                        .denom-table th { background: #f4f4f4; }
                        .text-right { text-align: right; }
                        .summary-box { background: #f9f9f9; border: 1px solid #ccc; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; }
                        .summary-box .row { display: flex; justify-content: space-between; margin-bottom: 4px; }
                        .summary-box .row.total { font-weight: bold; border-top: 1px solid #ddd; padding-top: 6px; margin-top: 6px; font-size: 14px; }
                        .sig-grid { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; }
                        .sig-line { width: 45%; border-top: 1px solid #333; text-align: center; font-size: 11px; padding-top: 5px; }
                        @media print { body { padding: 0; } }
                      </style>
                    </head>
                    <body>
                      <div class="header">
                        <h2>KAMAL EXPRESS TRAVEL & TOURS</h2>
                        <p>CASHIER SHIFT CLOSURE & HANDOVER AUDIT SLIP</p>
                      </div>

                      <table class="meta-table">
                        <tr>
                          <td><strong>Shift Date:</strong> ${shiftDate}</td>
                          <td class="text-right"><strong>Generated:</strong> ${new Date().toLocaleString()}</td>
                        </tr>
                        <tr>
                          <td><strong>Cashier:</strong> ${shiftAgent || "General Counter Staff"}</td>
                          <td class="text-right"><strong>Status:</strong> ${isBalanced ? "BALANCED ✓" : (diff > 0 ? "SURPLUS" : "SHORTAGE")}</td>
                        </tr>
                      </table>

                      <div class="summary-box">
                        <div class="row"><span>Total Shift Transactions:</span><span>${shiftData?.summary?.total_transactions || 0}</span></div>
                        <div class="row"><span>Total Gross Sales Volume:</span><span>Rs. ${(shiftData?.summary?.total_sales_volume || 0).toLocaleString()}</span></div>
                        <div class="row"><span>Expected Cash in Drawer:</span><span>Rs. ${(shiftData?.cash_drawer?.expected_cash || 0).toLocaleString()}</span></div>
                        <div class="row"><span>Physical Cash Counted:</span><span>Rs. ${totalPhysicalCash.toLocaleString()}</span></div>
                        <div class="row total"><span>Net Discrepancy:</span><span>${diff >= 0 ? `+Rs. ${diff.toLocaleString()}` : `-Rs. ${Math.abs(diff).toLocaleString()}`}</span></div>
                      </div>

                      <h4 style="font-size: 12px; margin-bottom: 6px; text-transform: uppercase;">Physical Denomination Breakdown</h4>
                      <table class="denom-table">
                        <thead>
                          <tr><th>Denomination</th><th class="text-right">Count</th><th class="text-right">Amount (PKR)</th></tr>
                        </thead>
                        <tbody>
                          <tr><td>Rs. 5,000</td><td class="text-right">${cashCounts[5000] || 0}</td><td class="text-right">Rs. ${((cashCounts[5000] || 0) * 5000).toLocaleString()}</td></tr>
                          <tr><td>Rs. 1,000</td><td class="text-right">${cashCounts[1000] || 0}</td><td class="text-right">Rs. ${((cashCounts[1000] || 0) * 1000).toLocaleString()}</td></tr>
                          <tr><td>Rs. 500</td><td class="text-right">${cashCounts[500] || 0}</td><td class="text-right">Rs. ${((cashCounts[500] || 0) * 500).toLocaleString()}</td></tr>
                          <tr><td>Rs. 100</td><td class="text-right">${cashCounts[100] || 0}</td><td class="text-right">Rs. ${((cashCounts[100] || 0) * 100).toLocaleString()}</td></tr>
                          <tr><td>Rs. 50 / 20 / 10 / Coins</td><td class="text-right">-</td><td class="text-right">Rs. ${(
                            (cashCounts[50] || 0) * 50 + (cashCounts[20] || 0) * 20 + (cashCounts[10] || 0) * 10 + (cashCounts.coins || 0)
                          ).toLocaleString()}</td></tr>
                        </tbody>
                      </table>

                      ${shiftNotes ? `<p style="font-size: 11px; margin-top: 10px;"><strong>Remarks:</strong> ${shiftNotes}</p>` : ""}

                      <div class="sig-grid">
                        <div class="sig-line">Cashier / Counter Staff Signature</div>
                        <div class="sig-line">Manager / Head Accountant Signature</div>
                      </div>

                      <script>
                        window.onload = function() { window.print(); }
                      </script>
                    </body>
                    </html>
                  `)
                  printWindow.document.close()
                }
              }}
            >
              <Printer className="w-4 h-4 mr-2" />
              Print Handover Slip
            </Button>

            <Button variant="default" onClick={() => setIsShiftModalOpen(false)}>
              Done
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
