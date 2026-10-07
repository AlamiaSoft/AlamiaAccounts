"use client"

import { useState, useEffect, useMemo } from "react"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Loader2, Search, CornerDownLeft, Eye, ExternalLink } from "lucide-react"
import { useAccounts } from "@/hooks/use-accounts"
import { useLedger } from "@/hooks/use-reports"
import { useTallyTableNavigation } from "@/hooks/use-tally-table-navigation"

interface LedgerViewProps {
  onNavigateToVoucher?: (reference: string) => void
  onSelectAccount?: (account: { name: string; code: string }) => void
}

export default function LedgerView({ onNavigateToVoucher, onSelectAccount }: LedgerViewProps) {
  const [selectedAccount, setSelectedAccount] = useState("")
  const [fromDate, setFromDate] = useState("2024-01-01")
  const [toDate, setToDate] = useState(new Date().toISOString().split("T")[0])
  const [filterType, setFilterType] = useState<"all" | "debit" | "credit">("all")
  const [searchTerm, setSearchTerm] = useState("")

  const { accounts: apiAccounts, isLoading: isLoadingAccounts } = useAccounts()

  useEffect(() => {
    if (!selectedAccount && apiAccounts && apiAccounts.length > 0) {
      const firstLeaf = apiAccounts.find((a: any) => !a.category) || apiAccounts[0]
      if (firstLeaf) {
        setSelectedAccount(firstLeaf.code)
      }
    }
  }, [apiAccounts, selectedAccount])

  const { data: ledgerData, isLoading: isLoadingLedger } = useLedger(selectedAccount, fromDate, toDate, "PKR")

  const currentAccount = useMemo(() => {
    return (apiAccounts || []).find((a: any) => a.code === selectedAccount)
  }, [apiAccounts, selectedAccount])

  const transactions = useMemo(() => {
    const entries = (ledgerData?.entries || []).map((entry: any, index: number) => ({
      id: index.toString(),
      date: entry.date,
      description: entry.description || "",
      voucherNo: entry.reference || "",
      debit: Number(entry.debit) || 0,
      credit: Number(entry.credit) || 0,
      balance: Number(entry.balance) || 0,
    }))

    return entries.filter((tx: any) => {
      // Type filter
      if (filterType === "debit" && tx.debit <= 0) return false
      if (filterType === "credit" && tx.credit <= 0) return false

      // Search filter
      if (!searchTerm.trim()) return true
      const term = searchTerm.toLowerCase()
      return (
        tx.voucherNo.toLowerCase().includes(term) ||
        tx.description.toLowerCase().includes(term) ||
        tx.date.includes(term) ||
        String(tx.debit).includes(term) ||
        String(tx.credit).includes(term)
      )
    })
  }, [ledgerData?.entries, filterType, searchTerm])

  const handleRowSelect = (index: number) => {
    const item = transactions[index]
    if (item?.voucherNo && onNavigateToVoucher) {
      onNavigateToVoucher(item.voucherNo)
    }
  }

  // Tally keyboard navigation: Up/Down arrow keys, Enter to open voucher
  const { selectedIndex, getRowProps } = useTallyTableNavigation({
    itemCount: transactions.length,
    onSelect: handleRowSelect,
    enabled: true,
  })

  const openingBalance = ledgerData?.opening_balance || 0
  const totalDebit = ledgerData?.total_debit || 0
  const totalCredit = ledgerData?.total_credit || 0
  const closingBalance = ledgerData?.closing_balance || 0

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <h2 className="text-3xl font-bold tracking-tight">General Ledger</h2>
            {currentAccount && (
              <Badge variant="secondary" className="font-mono text-xs">
                {currentAccount.code}
              </Badge>
            )}
          </div>
          <p className="text-muted-foreground mt-1">Universal transaction history for any Chart of Accounts ledger</p>
        </div>

        {currentAccount && onSelectAccount && (
          <Button
            variant="outline"
            size="sm"
            onClick={() => onSelectAccount({ name: currentAccount.name, code: currentAccount.code })}
            className="flex items-center gap-2 text-xs"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            Detailed View
          </Button>
        )}
      </div>

      {/* Account & Date Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="grid gap-4 md:grid-cols-4">
            <div>
              <label className="block text-xs font-medium text-muted-foreground mb-1.5">Select Account</label>
              <Select value={selectedAccount} onValueChange={setSelectedAccount}>
                <SelectTrigger>
                  <SelectValue placeholder={isLoadingAccounts ? "Loading accounts..." : "Select Account"} />
                </SelectTrigger>
                <SelectContent className="max-h-72">
                  {isLoadingAccounts ? (
                    <div className="flex items-center justify-center p-3 text-xs text-muted-foreground">
                      <Loader2 className="w-4 h-4 animate-spin mr-2" /> Loading accounts...
                    </div>
                  ) : (
                    (apiAccounts || []).map((acc: any) => (
                      <SelectItem key={acc.code} value={acc.code}>
                        <span className="font-mono text-xs text-muted-foreground mr-2">{acc.code}</span>
                        {acc.name}
                      </SelectItem>
                    ))
                  )}
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="block text-xs font-medium text-muted-foreground mb-1.5">From Date</label>
              <input
                type="date"
                value={fromDate}
                onChange={(e) => setFromDate(e.target.value)}
                className="w-full px-3 py-2 border rounded-md bg-background text-sm"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-muted-foreground mb-1.5">To Date</label>
              <input
                type="date"
                value={toDate}
                onChange={(e) => setToDate(e.target.value)}
                className="w-full px-3 py-2 border rounded-md bg-background text-sm"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-muted-foreground mb-1.5">Filter Postings</label>
              <Select value={filterType} onValueChange={(val: any) => setFilterType(val)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Transactions</SelectItem>
                  <SelectItem value="debit">Debits Only</SelectItem>
                  <SelectItem value="credit">Credits Only</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Account Summary Cards */}
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

      {/* Transactions Table */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <CardTitle className="text-lg flex items-center gap-2">
                <span>Account Transactions</span>
                <span className="text-xs font-normal text-muted-foreground">
                  ({transactions.length} entries)
                </span>
              </CardTitle>
              <CardDescription>
                {isLoadingLedger ? (
                  <span className="flex items-center text-xs">
                    <Loader2 className="w-3 h-3 animate-spin mr-1" /> Loading transactions...
                  </span>
                ) : (
                  <span className="text-xs">
                    Select a row and hit <kbd className="px-1 py-0.5 text-xs bg-muted border rounded">Enter</kbd> to inspect or edit the voucher.
                  </span>
                )}
              </CardDescription>
            </div>

            <div className="flex items-center gap-2">
              <div className="relative w-64">
                <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-muted-foreground" />
                <Input
                  placeholder="Filter particulars/voucher..."
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
                <kbd className="px-1.5 py-0.5 bg-background border rounded font-mono">↓</kbd> Select row
              </span>
              <span>
                <kbd className="px-1.5 py-0.5 bg-background border rounded font-mono">Enter</kbd> Open Voucher
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
                      {isLoadingLedger ? "Fetching ledger..." : "No transactions found matching your criteria."}
                    </td>
                  </tr>
                ) : (
                  transactions.map((tx: any, index: number) => {
                    const rowProps = getRowProps(index, "border-b border-border/40")
                    return (
                      <tr key={tx.id || index} {...rowProps}>
                        <td className="py-2.5 px-3 whitespace-nowrap font-mono text-xs">{tx.date}</td>
                        <td className="py-2.5 px-3 whitespace-nowrap">
                          <Badge variant="outline" className="text-[11px] font-mono font-medium">
                            {tx.voucherNo}
                          </Badge>
                        </td>
                        <td className="py-2.5 px-3 text-xs max-w-md truncate" title={tx.description}>
                          {tx.description}
                        </td>
                        <td className="py-2.5 px-3 text-right text-red-600 font-mono text-xs font-medium whitespace-nowrap">
                          {tx.debit > 0 ? `Rs. ${tx.debit.toLocaleString("en-IN")}` : "-"}
                        </td>
                        <td className="py-2.5 px-3 text-right text-green-600 font-mono text-xs font-medium whitespace-nowrap">
                          {tx.credit > 0 ? `Rs. ${tx.credit.toLocaleString("en-IN")}` : "-"}
                        </td>
                        <td className="py-2.5 px-3 text-right font-mono text-xs font-semibold whitespace-nowrap">
                          Rs. {tx.balance.toLocaleString("en-IN")}
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
