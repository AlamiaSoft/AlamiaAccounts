"use client"

import { useState, useMemo, useEffect } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import {
  Download,
  Printer,
  Loader2,
  Users,
  Search,
  CornerDownLeft,
  Calendar,
  DollarSign,
  Clock,
  ArrowRight,
} from "lucide-react"
import { useReceivables, useLedger } from "@/hooks/use-reports"
import { useTallyTableNavigation } from "@/hooks/use-tally-table-navigation"

interface SubledgerARProps {
  onNavigateToVoucher?: (reference: string) => void
  onBack?: () => void
}

export default function SubledgerAR({ onNavigateToVoucher, onBack }: SubledgerARProps) {
  const [asOfDate, setAsOfDate] = useState(() => new Date().toISOString().split("T")[0])
  const [fromDate, setFromDate] = useState("2024-01-01")
  const [selectedCustomerCode, setSelectedCustomerCode] = useState<string>("")
  const [searchTerm, setSearchTerm] = useState("")
  const [partySearchTerm, setPartySearchTerm] = useState("")

  const { data: receivablesData, isLoading: isLoadingAR } = useReceivables(asOfDate, "PKR")

  const customers = useMemo(() => {
    return receivablesData?.customers || []
  }, [receivablesData?.customers])

  // Select first customer by default once loaded if none selected
  useEffect(() => {
    if (!selectedCustomerCode && customers.length > 0) {
      setSelectedCustomerCode(customers[0].code)
    }
  }, [customers, selectedCustomerCode])

  const selectedCustomer = useMemo(() => {
    return customers.find((c: any) => c.code === selectedCustomerCode) || customers[0]
  }, [customers, selectedCustomerCode])

  const { data: customerLedgerData, isLoading: isLoadingLedger } = useLedger(
    selectedCustomerCode || (customers[0]?.code ?? ""),
    fromDate,
    asOfDate,
    "PKR"
  )

  const transactions = useMemo(() => {
    const entries = (customerLedgerData?.entries || []).map((entry: any, index: number) => ({
      id: String(index + 1),
      date: entry.date,
      voucherNo: entry.reference || `VCH-${index + 1}`,
      particulars: entry.description || "Customer transaction",
      debit: Number(entry.debit) || 0, // Invoiced / Billed
      credit: Number(entry.credit) || 0, // Payment received / Credit note
      balance: Number(entry.balance) || 0,
    }))

    if (!searchTerm.trim()) return entries
    const term = searchTerm.toLowerCase()
    return entries.filter(
      (tx: any) =>
        tx.voucherNo.toLowerCase().includes(term) ||
        tx.particulars.toLowerCase().includes(term) ||
        tx.date.includes(term) ||
        String(tx.debit).includes(term) ||
        String(tx.credit).includes(term)
    )
  }, [customerLedgerData?.entries, searchTerm])

  const filteredCustomers = useMemo(() => {
    if (!partySearchTerm.trim()) return customers
    const term = partySearchTerm.toLowerCase()
    return customers.filter(
      (c: any) =>
        c.name.toLowerCase().includes(term) ||
        c.code.toLowerCase().includes(term)
    )
  }, [customers, partySearchTerm])

  const handleRowSelect = (index: number) => {
    const item = transactions[index]
    if (item?.voucherNo && onNavigateToVoucher) {
      onNavigateToVoucher(item.voucherNo)
    }
  }

  // Tally keyboard navigation: ArrowUp/ArrowDown, Enter to open voucher, Esc to return
  const { selectedIndex, getRowProps } = useTallyTableNavigation({
    itemCount: transactions.length,
    onSelect: handleRowSelect,
    onEscape: onBack,
    enabled: true,
  })

  const agingSummary = receivablesData?.aging_summary || {
    current_0_30: 0,
    aging_31_60: 0,
    aging_61_90: 0,
    aging_90_plus: 0,
  }

  const totalReceivables = Number(receivablesData?.total_receivables) || 0
  const openingBalance = Number(customerLedgerData?.opening_balance) || 0
  const totalBilled = Number(customerLedgerData?.total_debit) || 0
  const totalReceived = Number(customerLedgerData?.total_credit) || 0
  const closingBalance = Number(customerLedgerData?.closing_balance) || 0

  const handlePrint = () => {
    window.print()
  }

  const handleExportCSV = () => {
    const headers = ["Date", "Voucher No", "Customer", "Particulars", "Invoiced / Dr", "Received / Cr", "Balance"]
    const rows = transactions.map((t: any) => [
      t.date,
      t.voucherNo,
      `"${(selectedCustomer?.name || selectedCustomerCode).replace(/"/g, '""')}"`,
      `"${t.particulars.replace(/"/g, '""')}"`,
      t.debit,
      t.credit,
      t.balance,
    ])
    const csvContent =
      "data:text/csv;charset=utf-8," +
      [headers.join(","), ...rows.map((r: any) => r.join(","))].join("\n")
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute("download", `AR_Subledger_${selectedCustomerCode}_${asOfDate}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <div className="flex items-center gap-3">
            <Users className="w-7 h-7 text-primary" />
            <h2 className="text-3xl font-bold tracking-tight">Accounts Receivable (AR) Subledger</h2>
          </div>
          <p className="text-muted-foreground mt-1">
            Customer directory, aging analysis, and invoice settlement ledger
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={handleExportCSV}
            disabled={transactions.length === 0}
          >
            <Download className="w-4 h-4 mr-2" />
            Export CSV
          </Button>
          <Button variant="outline" size="sm" onClick={handlePrint}>
            <Printer className="w-4 h-4 mr-2" />
            Print
          </Button>
        </div>
      </div>

      {/* Aging & KPI Breakdown */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
        <Card className="border-l-4 border-l-primary">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs font-semibold uppercase">Total AR Outstanding</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-primary">
              Rs. {totalReceivables.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs text-green-600 font-medium">0 - 30 Days (Current)</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-green-600">
              Rs. {agingSummary.current_0_30.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs text-yellow-600 font-medium">31 - 60 Days</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-yellow-600">
              Rs. {agingSummary.aging_31_60.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs text-orange-600 font-medium">61 - 90 Days</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-orange-600">
              Rs. {agingSummary.aging_61_90.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs text-red-600 font-medium">90+ Days (Overdue)</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-red-600">
              Rs. {agingSummary.aging_90_plus.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Main Subledger Grid: Party Directory & Ledger Detail */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: Customer Directory */}
        <Card className="lg:col-span-1">
          <CardHeader className="pb-3">
            <CardTitle className="text-base flex items-center justify-between">
              <span>Customer Accounts</span>
              <Badge variant="secondary" className="text-xs font-mono">
                {customers.length}
              </Badge>
            </CardTitle>
            <div className="relative mt-2">
              <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-muted-foreground" />
              <Input
                placeholder="Search customers..."
                value={partySearchTerm}
                onChange={(e) => setPartySearchTerm(e.target.value)}
                className="pl-8 text-xs h-9"
              />
            </div>
          </CardHeader>
          <CardContent className="p-0">
            <div className="max-h-[520px] overflow-y-auto divide-y divide-border/60">
              {isLoadingAR ? (
                <div className="p-6 text-center text-xs text-muted-foreground">
                  <Loader2 className="w-4 h-4 animate-spin mx-auto mb-2" /> Loading customers...
                </div>
              ) : filteredCustomers.length === 0 ? (
                <div className="p-6 text-center text-xs text-muted-foreground">
                  No customer receivable accounts found.
                </div>
              ) : (
                filteredCustomers.map((cust: any) => {
                  const isSelected = cust.code === selectedCustomerCode
                  return (
                    <div
                      key={cust.code}
                      onClick={() => setSelectedCustomerCode(cust.code)}
                      className={`p-3.5 cursor-pointer transition-colors text-sm flex items-center justify-between ${
                        isSelected
                          ? "bg-primary/10 border-l-4 border-l-primary font-medium text-foreground"
                          : "hover:bg-muted/50"
                      }`}
                    >
                      <div className="space-y-0.5 truncate pr-2">
                        <div className="flex items-center gap-2">
                          <span className="font-mono text-xs text-muted-foreground">{cust.code}</span>
                          <span className="font-semibold text-xs truncate">{cust.name}</span>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                          0-30d: Rs. {(cust.aging?.current_0_30 || 0).toLocaleString("en-IN")}
                        </p>
                      </div>
                      <div className="text-right whitespace-nowrap">
                        <p className="font-mono text-xs font-bold text-primary">
                          Rs. {Number(cust.balance).toLocaleString("en-IN")}
                        </p>
                        <ArrowRight className={`w-3.5 h-3.5 ml-auto text-muted-foreground mt-1 ${isSelected ? "text-primary" : "opacity-0"}`} />
                      </div>
                    </div>
                  )
                })
              )}
            </div>
          </CardContent>
        </Card>

        {/* Right: Selected Customer Subledger Transactions */}
        <Card className="lg:col-span-2">
          <CardHeader className="pb-3">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <div className="flex items-center gap-2">
                  <CardTitle className="text-base">
                    {selectedCustomer?.name || "Customer Ledger"}
                  </CardTitle>
                  {selectedCustomerCode && (
                    <Badge variant="outline" className="font-mono text-xs">
                      {selectedCustomerCode}
                    </Badge>
                  )}
                </div>
                <CardDescription className="text-xs mt-1">
                  Balance: <strong className="text-primary font-mono font-bold">Rs. {closingBalance.toLocaleString("en-IN")}</strong> | Opening: Rs. {openingBalance.toLocaleString("en-IN")}
                </CardDescription>
              </div>

              <div className="flex items-center gap-2">
                <div className="relative w-48">
                  <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-muted-foreground" />
                  <Input
                    placeholder="Filter vouchers..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-8 text-xs h-8"
                  />
                </div>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {/* Tally Keyboard Shortcut Hint Bar */}
            <div className="mb-2 px-3 py-1 bg-muted/40 rounded-md border border-border/50 text-[11px] text-muted-foreground flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <span>
                  <kbd className="px-1 py-0.5 bg-background border rounded font-mono">↑</kbd>{" "}
                  <kbd className="px-1 py-0.5 bg-background border rounded font-mono">↓</kbd> Navigate
                </span>
                <span>
                  <kbd className="px-1 py-0.5 bg-background border rounded font-mono">Enter</kbd> Open Voucher
                </span>
              </div>
              {transactions.length > 0 && selectedIndex >= 0 && (
                <span className="font-mono text-primary font-medium">
                  Row {selectedIndex + 1} of {transactions.length}
                </span>
              )}
            </div>

            <div className="overflow-x-auto border rounded-md max-h-[460px] overflow-y-auto">
              <table className="w-full text-xs">
                <thead className="bg-muted/50 border-b sticky top-0 z-10">
                  <tr>
                    <th className="text-left py-2 px-2.5 font-semibold text-muted-foreground uppercase">Date</th>
                    <th className="text-left py-2 px-2.5 font-semibold text-muted-foreground uppercase">Voucher</th>
                    <th className="text-left py-2 px-2.5 font-semibold text-muted-foreground uppercase">Particulars</th>
                    <th className="text-right py-2 px-2.5 font-semibold text-muted-foreground uppercase">Invoiced (Dr)</th>
                    <th className="text-right py-2 px-2.5 font-semibold text-muted-foreground uppercase">Received (Cr)</th>
                    <th className="text-right py-2 px-2.5 font-semibold text-muted-foreground uppercase">Balance</th>
                    <th className="text-center py-2 px-1 font-semibold text-muted-foreground uppercase w-10"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/60">
                  {isLoadingLedger ? (
                    <tr>
                      <td colSpan={7} className="py-8 text-center text-muted-foreground">
                        <Loader2 className="w-4 h-4 animate-spin mx-auto mb-1" /> Loading transactions...
                      </td>
                    </tr>
                  ) : transactions.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="py-8 text-center text-muted-foreground">
                        No transactions found for this customer account.
                      </td>
                    </tr>
                  ) : (
                    transactions.map((tx: any, index: number) => {
                      const rowProps = getRowProps(index, "border-b border-border/40")
                      return (
                        <tr key={tx.id || index} {...rowProps}>
                          <td className="py-2 px-2.5 whitespace-nowrap font-mono">{tx.date}</td>
                          <td className="py-2 px-2.5 whitespace-nowrap">
                            <Badge variant="outline" className="text-[10px] font-mono font-medium">
                              {tx.voucherNo}
                            </Badge>
                          </td>
                          <td className="py-2 px-2.5 max-w-xs truncate" title={tx.particulars}>
                            {tx.particulars}
                          </td>
                          <td className="py-2 px-2.5 text-right text-blue-600 font-mono font-medium whitespace-nowrap">
                            {tx.debit > 0 ? `Rs. ${tx.debit.toLocaleString("en-IN")}` : "-"}
                          </td>
                          <td className="py-2 px-2.5 text-right text-green-600 font-mono font-medium whitespace-nowrap">
                            {tx.credit > 0 ? `Rs. ${tx.credit.toLocaleString("en-IN")}` : "-"}
                          </td>
                          <td className="py-2 px-2.5 text-right font-mono font-bold whitespace-nowrap">
                            Rs. {tx.balance.toLocaleString("en-IN")}
                          </td>
                          <td className="py-2 px-1 text-center">
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-6 w-6 p-0 text-primary hover:text-primary hover:bg-primary/10"
                              onClick={(e) => {
                                e.stopPropagation()
                                handleRowSelect(index)
                              }}
                              title="Open Voucher"
                            >
                              <CornerDownLeft className="w-3 h-3" />
                            </Button>
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
                {transactions.length > 0 && (
                  <tfoot className="bg-muted/60 font-bold border-t sticky bottom-0 z-10">
                    <tr>
                      <td className="py-2 px-2.5" colSpan={3}>
                        Customer Totals
                      </td>
                      <td className="py-2 px-2.5 text-right text-blue-600 font-mono">
                        Rs. {totalBilled.toLocaleString("en-IN")}
                      </td>
                      <td className="py-2 px-2.5 text-right text-green-600 font-mono">
                        Rs. {totalReceived.toLocaleString("en-IN")}
                      </td>
                      <td className="py-2 px-2.5 text-right font-mono">
                        Rs. {closingBalance.toLocaleString("en-IN")}
                      </td>
                      <td></td>
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
