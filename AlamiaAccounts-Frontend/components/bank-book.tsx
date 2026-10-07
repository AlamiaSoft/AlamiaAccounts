"use client"

import { useState, useMemo } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import {
  Download,
  Printer,
  Loader2,
  Building2,
  ArrowDownLeft,
  ArrowUpRight,
  ArrowLeftRight,
  Search,
  CornerDownLeft,
} from "lucide-react"
import { useAccounts } from "@/hooks/use-accounts"
import { useBankBook } from "@/hooks/use-reports"
import { useTallyTableNavigation } from "@/hooks/use-tally-table-navigation"

interface BankBookProps {
  onNavigateToVoucher?: (reference: string) => void
  onBack?: () => void
}

export default function BankBook({ onNavigateToVoucher, onBack }: BankBookProps) {
  const { accounts: allAccounts, isLoading: isLoadingAccounts } = useAccounts()

  // Filter bank posting accounts (under 1120 Bank Accounts or having 'bank' in name)
  const bankAccounts = useMemo(() => {
    return (allAccounts || []).filter(
      (acc: any) =>
        !acc.category &&
        (acc.code.startsWith("112") ||
          acc.parent_code === "1120" ||
          acc.name.toLowerCase().includes("bank") ||
          acc.name.toLowerCase().includes("meezan") ||
          acc.name.toLowerCase().includes("hbl") ||
          acc.name.toLowerCase().includes("mcb") ||
          acc.name.toLowerCase().includes("ubl"))
    )
  }, [allAccounts])

  const [selectedBank, setSelectedBank] = useState<string>("ALL")
  const [fromDate, setFromDate] = useState(() => {
    const d = new Date()
    return `${d.getFullYear()}-01-01`
  })
  const [toDate, setToDate] = useState(() => new Date().toISOString().split("T")[0])
  const [searchTerm, setSearchTerm] = useState("")

  const bankParam = selectedBank === "ALL" ? undefined : selectedBank
  const { data: bankData, isLoading: isLoadingBankBook } = useBankBook(bankParam, fromDate, toDate, "PKR")

  const transactions = useMemo(() => {
    const entries = (bankData?.entries || []).map((entry: any, index: number) => {
      const ref = (entry.reference || "").toLowerCase()
      const desc = (entry.description || "").toLowerCase()
      const rawType = (entry.voucher_type || "").toLowerCase()

      let txType: "deposit" | "withdrawal" | "contra" = "withdrawal"
      if (
        rawType === "contra" ||
        ref.startsWith("cv") ||
        desc.includes("contra") ||
        desc.includes("internal transfer")
      ) {
        txType = "contra"
      } else if (Number(entry.inflow || entry.debit || 0) > 0) {
        txType = "deposit"
      } else {
        txType = "withdrawal"
      }

      return {
        id: String(index + 1),
        date: entry.date,
        voucherNo: entry.reference || `VCH-${index + 1}`,
        txType,
        particulars: entry.description || "Bank movement",
        accountName: entry.account_name || "",
        accountCode: entry.account_code || "",
        inflow: Number(entry.inflow ?? entry.debit ?? 0),
        outflow: Number(entry.outflow ?? entry.credit ?? 0),
        balance: Number(entry.balance ?? 0),
      }
    })

    if (!searchTerm.trim()) return entries
    const term = searchTerm.toLowerCase()
    return entries.filter(
      (tx: any) =>
        tx.voucherNo.toLowerCase().includes(term) ||
        tx.particulars.toLowerCase().includes(term) ||
        tx.accountName.toLowerCase().includes(term) ||
        tx.accountCode.toLowerCase().includes(term) ||
        tx.date.includes(term) ||
        String(tx.inflow).includes(term) ||
        String(tx.outflow).includes(term)
    )
  }, [bankData?.entries, searchTerm])

  const handleRowSelect = (index: number) => {
    const item = transactions[index]
    if (item?.voucherNo && onNavigateToVoucher) {
      onNavigateToVoucher(item.voucherNo)
    }
  }

  // Tally keyboard navigation: ArrowUp/ArrowDown, Enter to open voucher, Esc to go back
  const { selectedIndex, getRowProps } = useTallyTableNavigation({
    itemCount: transactions.length,
    onSelect: handleRowSelect,
    onEscape: onBack,
    enabled: true,
  })

  const openingBalance = Number(bankData?.opening_balance) || 0
  const totalInflow = Number(bankData?.total_inflows ?? bankData?.total_debit ?? 0)
  const totalOutflow = Number(bankData?.total_outflows ?? bankData?.total_credit ?? 0)
  const closingBalance =
    Number(bankData?.closing_balance) || openingBalance + totalInflow - totalOutflow

  const handlePrint = () => {
    window.print()
  }

  const handleExportCSV = () => {
    const headers = [
      "Date",
      "Voucher No",
      "Type",
      "Account",
      "Particulars",
      "Deposits / Inflow (Dr)",
      "Withdrawals / Outflow (Cr)",
      "Balance",
    ]
    const rows = transactions.map((t: any) => [
      t.date,
      t.voucherNo,
      t.txType.toUpperCase(),
      `"${(t.accountName || t.accountCode).replace(/"/g, '""')}"`,
      `"${t.particulars.replace(/"/g, '""')}"`,
      t.inflow,
      t.outflow,
      t.balance,
    ])
    const csvContent =
      "data:text/csv;charset=utf-8," +
      [headers.join(","), ...rows.map((r: any) => r.join(","))].join("\n")
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute(
      "download",
      `BankBook_${selectedBank}_${fromDate}_to_${toDate}.csv`
    )
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
            <Building2 className="w-7 h-7 text-primary" />
            <h2 className="text-3xl font-bold tracking-tight">Bank Book</h2>
          </div>
          <p className="text-muted-foreground mt-1">
            Dedicated transaction journal for corporate bank accounts, deposits, and transfers
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

      {/* Account & Date Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1.5 block">
                Bank Account
              </label>
              {isLoadingAccounts ? (
                <div className="h-10 border rounded-md flex items-center px-3 text-xs text-muted-foreground">
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" /> Loading accounts...
                </div>
              ) : (
                <Select value={selectedBank} onValueChange={(val) => setSelectedBank(val)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select bank account" />
                  </SelectTrigger>
                  <SelectContent className="max-h-72">
                    <SelectItem value="ALL">
                      <span className="font-semibold">All Bank Accounts (Consolidated)</span>
                    </SelectItem>
                    {bankAccounts.map((acc: any) => (
                      <SelectItem key={acc.code} value={acc.code}>
                        <span className="font-mono text-xs text-muted-foreground mr-2">
                          {acc.code}
                        </span>
                        {acc.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </div>

            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1.5 block">
                From Date
              </label>
              <Input
                type="date"
                value={fromDate}
                onChange={(e) => setFromDate(e.target.value)}
                className="text-sm"
              />
            </div>

            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1.5 block">
                To Date
              </label>
              <Input
                type="date"
                value={toDate}
                onChange={(e) => setToDate(e.target.value)}
                className="text-sm"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Summary KPI Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs">Opening Bank Balance</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold">Rs. {openingBalance.toLocaleString("en-IN")}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 flex flex-row items-center justify-between">
            <CardDescription className="text-xs">Total Deposits (Inflows)</CardDescription>
            <ArrowDownLeft className="w-4 h-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-green-600">
              Rs. {totalInflow.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 flex flex-row items-center justify-between">
            <CardDescription className="text-xs">Total Withdrawals (Outflows)</CardDescription>
            <ArrowUpRight className="w-4 h-4 text-red-600" />
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-red-600">
              Rs. {totalOutflow.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs">Closing Bank Balance</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-bold text-primary">
              Rs. {closingBalance.toLocaleString("en-IN")}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Bank Transactions Table */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <CardTitle className="text-lg flex items-center gap-2">
                <span>Bank Statement & Movements</span>
                <span className="text-xs font-normal text-muted-foreground">
                  ({transactions.length} transactions)
                </span>
              </CardTitle>
              <CardDescription>
                {isLoadingBankBook ? (
                  <span className="flex items-center text-xs">
                    <Loader2 className="w-3 h-3 animate-spin mr-1" /> Fetching bank entries...
                  </span>
                ) : (
                  <span className="text-xs">
                    Navigate with <kbd className="px-1 py-0.5 text-xs bg-muted border rounded">↑</kbd> <kbd className="px-1 py-0.5 text-xs bg-muted border rounded">↓</kbd> and hit <kbd className="px-1 py-0.5 text-xs bg-muted border rounded">Enter</kbd> to drill down to voucher.
                  </span>
                )}
              </CardDescription>
            </div>

            <div className="flex items-center gap-2">
              <div className="relative w-64">
                <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-muted-foreground" />
                <Input
                  placeholder="Filter bank transactions..."
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
                  <th className="text-left py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Voucher No</th>
                  <th className="text-left py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Account / Bank</th>
                  <th className="text-left py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Particulars</th>
                  <th className="text-right py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Deposits (Dr)</th>
                  <th className="text-right py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Withdrawals (Cr)</th>
                  <th className="text-right py-2.5 px-3 font-semibold text-xs text-muted-foreground uppercase tracking-wider">Balance</th>
                  <th className="text-center py-2.5 px-2 font-semibold text-xs text-muted-foreground uppercase tracking-wider w-16">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/60">
                {transactions.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="py-8 text-center text-muted-foreground text-sm">
                      {isLoadingBankBook
                        ? "Fetching bank book..."
                        : "No bank transactions recorded for the selected period."}
                    </td>
                  </tr>
                ) : (
                  transactions.map((tx: any, index: number) => {
                    const rowProps = getRowProps(index, "border-b border-border/40")
                    return (
                      <tr key={tx.id || index} {...rowProps}>
                        <td className="py-2.5 px-3 whitespace-nowrap font-mono text-xs">{tx.date}</td>
                        <td className="py-2.5 px-3 whitespace-nowrap">
                          <div className="flex items-center gap-1.5">
                            <Badge variant="outline" className="text-[11px] font-mono font-medium">
                              {tx.voucherNo}
                            </Badge>
                            {tx.txType === "contra" && (
                              <Badge
                                variant="secondary"
                                className="text-[10px] px-1 py-0 bg-blue-500/10 text-blue-600 border-blue-200"
                              >
                                <ArrowLeftRight className="w-2.5 h-2.5 mr-0.5 inline" /> Contra
                              </Badge>
                            )}
                          </div>
                        </td>
                        <td className="py-2.5 px-3 text-xs whitespace-nowrap">
                          <span className="font-mono text-muted-foreground mr-1.5">{tx.accountCode}</span>
                          <span className="font-medium">{tx.accountName || "Bank Account"}</span>
                        </td>
                        <td className="py-2.5 px-3 text-xs max-w-md truncate" title={tx.particulars}>
                          {tx.particulars}
                        </td>
                        <td className="py-2.5 px-3 text-right text-green-600 font-mono text-xs font-medium whitespace-nowrap">
                          {tx.inflow > 0 ? `Rs. ${tx.inflow.toLocaleString("en-IN")}` : "-"}
                        </td>
                        <td className="py-2.5 px-3 text-right text-red-600 font-mono text-xs font-medium whitespace-nowrap">
                          {tx.outflow > 0 ? `Rs. ${tx.outflow.toLocaleString("en-IN")}` : "-"}
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
                    <td className="py-2.5 px-3" colSpan={4}>
                      Total Bank Movements
                    </td>
                    <td className="py-2.5 px-3 text-right text-green-600 font-mono">
                      Rs. {totalInflow.toLocaleString("en-IN")}
                    </td>
                    <td className="py-2.5 px-3 text-right text-red-600 font-mono">
                      Rs. {totalOutflow.toLocaleString("en-IN")}
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
