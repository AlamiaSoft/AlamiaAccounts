"use client"

import { useState, useMemo } from "react"
import { ArrowLeft, Download, Filter, Loader2, Search, CornerDownLeft, ArrowUpDown } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Input } from "@/components/ui/input"
import { useLedger } from "@/hooks/use-reports"
import { useAccounts } from "@/hooks/use-accounts"
import { useTallyTableNavigation } from "@/hooks/use-tally-table-navigation"

interface LedgerDetailViewProps {
  accountName: string
  accountCode: string
  onBack: () => void
  onNavigateToVoucher?: (reference: string) => void
  onSelectAccount?: (account: { name: string; code: string }) => void
}

export default function LedgerDetailView({
  accountName: initialAccountName,
  accountCode: initialAccountCode,
  onBack,
  onNavigateToVoucher,
  onSelectAccount,
}: LedgerDetailViewProps) {
  const [currentAccountCode, setCurrentAccountCode] = useState(initialAccountCode)
  const [fromDate, setFromDate] = useState("2024-01-01")
  const [toDate, setToDate] = useState(new Date().toISOString().split("T")[0])
  const [showFilters, setShowFilters] = useState(false)
  const [searchTerm, setSearchTerm] = useState("")

  const { accounts: apiAccounts } = useAccounts()
  const { data: ledgerData, isLoading } = useLedger(currentAccountCode, fromDate, toDate, "PKR")

  // Current account name
  const currentAccountName = useMemo(() => {
    const acc = (apiAccounts || []).find((a: any) => a.code === currentAccountCode)
    return acc ? acc.name : initialAccountName
  }, [apiAccounts, currentAccountCode, initialAccountName])

  // Filtered transactions
  const transactions = useMemo(() => {
    const entries = (ledgerData?.entries || []).map((entry: any, index: number) => ({
      id: index.toString(),
      date: entry.date,
      voucherType: entry.reference?.split("-")[0] || "JV",
      voucherNumber: entry.reference || "",
      particulars: entry.description || "",
      debit: Number(entry.debit) || 0,
      credit: Number(entry.credit) || 0,
      balance: Number(entry.balance) || 0,
    }))

    if (!searchTerm.trim()) return entries

    const term = searchTerm.toLowerCase()
    return entries.filter(
      (tx: any) =>
        tx.voucherNumber.toLowerCase().includes(term) ||
        tx.particulars.toLowerCase().includes(term) ||
        tx.date.includes(term) ||
        String(tx.debit).includes(term) ||
        String(tx.credit).includes(term)
    )
  }, [ledgerData?.entries, searchTerm])

  const handleRowSelect = (index: number) => {
    const item = transactions[index]
    if (item?.voucherNumber && onNavigateToVoucher) {
      onNavigateToVoucher(item.voucherNumber)
    }
  }

  // Tally keyboard navigation: Up/Down arrow keys, Enter to open voucher, Esc to go back
  const { selectedIndex, getRowProps } = useTallyTableNavigation({
    itemCount: transactions.length,
    onSelect: handleRowSelect,
    onEscape: onBack,
    enabled: true,
  })

  const totalDebit = ledgerData?.total_debit || 0
  const totalCredit = ledgerData?.total_credit || 0
  const closingBalance = ledgerData?.closing_balance || 0
  const openingBalance = ledgerData?.opening_balance || 0

  const handleAccountChange = (newCode: string) => {
    setCurrentAccountCode(newCode)
    if (onSelectAccount) {
      const acc = (apiAccounts || []).find((a: any) => a.code === newCode)
      onSelectAccount({
        code: newCode,
        name: acc?.name || `Account ${newCode}`,
      })
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={onBack} title="Press [Esc] to go back">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back
          </Button>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-3xl font-bold tracking-tight">Ledger: {currentAccountName}</h1>
              <Badge variant="secondary" className="font-mono text-sm">
                {currentAccountCode}
              </Badge>
            </div>
            <p className="text-muted-foreground mt-1">Detailed transaction drilldown & account movements</p>
          </div>
        </div>

        {/* Account Quick Switcher */}
        <div className="flex flex-wrap items-center gap-2">
          {apiAccounts && apiAccounts.length > 0 && (
            <div className="w-64">
              <Select value={currentAccountCode} onValueChange={handleAccountChange}>
                <SelectTrigger>
                  <SelectValue placeholder="Switch Account" />
                </SelectTrigger>
                <SelectContent className="max-h-72">
                  {apiAccounts.map((acc: any) => (
                    <SelectItem key={acc.code} value={acc.code}>
                      <span className="font-mono text-xs text-muted-foreground mr-2">{acc.code}</span>
                      {acc.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <Button variant="outline" onClick={() => setShowFilters(!showFilters)}>
            <Filter className="w-4 h-4 mr-2" />
            Filter
          </Button>
        </div>
      </div>

      {/* Filter drawer / card */}
      {showFilters && (
        <Card className="bg-muted/30">
          <CardContent className="pt-6">
            <div className="flex flex-col md:flex-row gap-4 items-end">
              <div className="flex-1">
                <label className="block text-xs font-medium text-muted-foreground mb-1.5">From Date</label>
                <input
                  type="date"
                  value={fromDate}
                  onChange={(e) => setFromDate(e.target.value)}
                  className="w-full px-3 py-2 border rounded-md bg-background text-sm"
                />
              </div>
              <div className="flex-1">
                <label className="block text-xs font-medium text-muted-foreground mb-1.5">To Date</label>
                <input
                  type="date"
                  value={toDate}
                  onChange={(e) => setToDate(e.target.value)}
                  className="w-full px-3 py-2 border rounded-md bg-background text-sm"
                />
              </div>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    const now = new Date()
                    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split("T")[0]
                    setFromDate(firstDay)
                    setToDate(now.toISOString().split("T")[0])
                  }}
                >
                  This Month
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    setFromDate("2024-01-01")
                    setToDate(new Date().toISOString().split("T")[0])
                  }}
                >
                  All Time
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Opening Balance</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold">Rs. {openingBalance.toLocaleString("en-IN")}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Debit</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-red-600">Rs. {totalDebit.toLocaleString("en-IN")}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Credit</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-green-600">Rs. {totalCredit.toLocaleString("en-IN")}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Closing Balance</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-primary">Rs. {closingBalance.toLocaleString("en-IN")}</p>
          </CardContent>
        </Card>
      </div>

      {/* Transactions Table & Tally Keyboard Instruction */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <CardTitle className="text-lg flex items-center gap-2">
                <span>Transaction History</span>
                <span className="text-xs font-normal text-muted-foreground">
                  ({transactions.length} entries)
                </span>
              </CardTitle>
              <CardDescription>
                {isLoading ? (
                  <span className="flex items-center text-xs">
                    <Loader2 className="w-3 h-3 animate-spin mr-1" /> Fetching ledger...
                  </span>
                ) : (
                  <span className="text-xs">
                    Double-click or press <kbd className="px-1.5 py-0.5 text-xs bg-muted border rounded">Enter</kbd> on any row to view/edit voucher.
                  </span>
                )}
              </CardDescription>
            </div>

            {/* In-table Search Bar */}
            <div className="flex items-center gap-2">
              <div className="relative w-64">
                <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-muted-foreground" />
                <Input
                  placeholder="Filter transactions..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-8 text-xs h-9"
                />
              </div>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {/* Tally Keyboard Shortcut Hint Bar */}
          <div className="mb-2 px-3 py-1.5 bg-muted/40 rounded-md border border-border/50 text-[11px] text-muted-foreground flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-3">
              <span>
                <kbd className="px-1.5 py-0.5 bg-background border rounded font-mono">↑</kbd>{" "}
                <kbd className="px-1.5 py-0.5 bg-background border rounded font-mono">↓</kbd> Navigate rows
              </span>
              <span>
                <kbd className="px-1.5 py-0.5 bg-background border rounded font-mono">Enter</kbd> Open Voucher
              </span>
              <span>
                <kbd className="px-1.5 py-0.5 bg-background border rounded font-mono">Esc</kbd> Back
              </span>
            </div>
            {transactions.length > 0 && selectedIndex >= 0 && (
              <span className="font-mono text-primary font-medium">
                Row {selectedIndex + 1} of {transactions.length}
              </span>
            )}
          </div>

          <div className="overflow-x-auto border rounded-md">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 border-b">
                <tr>
                  <th className="text-left py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Date</th>
                  <th className="text-left py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Voucher</th>
                  <th className="text-left py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Particulars</th>
                  <th className="text-right py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Debit</th>
                  <th className="text-right py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Credit</th>
                  <th className="text-right py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Balance</th>
                  <th className="text-center py-2.5 px-2 font-semibold text-xs text-muted-foreground uppercase tracking-wider w-16">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/60">
                {transactions.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="py-8 text-center text-muted-foreground text-sm">
                      {isLoading ? "Loading transactions..." : "No transactions recorded for this period."}
                    </td>
                  </tr>
                ) : (
                  transactions.map((transaction: any, index: number) => {
                    const rowProps = getRowProps(index, "border-b border-border/40")
                    return (
                      <tr key={transaction.id || index} {...rowProps}>
                        <td className="py-2.5 px-3 whitespace-nowrap font-mono text-xs">
                          {transaction.date}
                        </td>
                        <td className="py-2.5 px-3 whitespace-nowrap">
                          <div className="flex items-center gap-1.5">
                            <Badge variant="outline" className="text-[11px] font-mono font-medium">
                              {transaction.voucherNumber}
                            </Badge>
                          </div>
                        </td>
                        <td className="py-2.5 px-3 text-xs max-w-md truncate" title={transaction.particulars}>
                          {transaction.particulars}
                        </td>
                        <td className="py-2.5 px-3 text-right text-red-600 font-mono text-xs font-medium whitespace-nowrap">
                          {transaction.debit > 0 ? `Rs. ${transaction.debit.toLocaleString("en-IN")}` : "-"}
                        </td>
                        <td className="py-2.5 px-3 text-right text-green-600 font-mono text-xs font-medium whitespace-nowrap">
                          {transaction.credit > 0 ? `Rs. ${transaction.credit.toLocaleString("en-IN")}` : "-"}
                        </td>
                        <td className="py-2.5 px-3 text-right font-mono text-xs font-semibold whitespace-nowrap">
                          Rs. {transaction.balance.toLocaleString("en-IN")}
                        </td>
                        <td className="py-2.5 px-2 text-center">
                          <Button
                            size="sm"
                            variant="ghost"
                            className="h-7 px-2 text-xs text-primary hover:text-primary hover:bg-primary/10"
                            onClick={(e) => {
                              e.stopPropagation()
                              handleRowSelect(index)
                            }}
                            title="Open Voucher"
                          >
                            <CornerDownLeft className="w-3.5 h-3.5" />
                          </Button>
                        </td>
                      </tr>
                    )
                  })
                )}
              </tbody>
              {transactions.length > 0 && (
                <tfoot className="bg-muted/60 font-bold border-t-2 text-xs">
                  <tr>
                    <td className="py-2.5 px-3" colSpan={3}>
                      Total Movements
                    </td>
                    <td className="py-2.5 px-3 text-right text-red-600 font-mono">
                      Rs. {totalDebit.toLocaleString("en-IN")}
                    </td>
                    <td className="py-2.5 px-3 text-right text-green-600 font-mono">
                      Rs. {totalCredit.toLocaleString("en-IN")}
                    </td>
                    <td className="py-2.5 px-3 text-right font-mono">
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
  )
}
