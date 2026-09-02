"use client"

import { useState, useMemo } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Input } from "@/components/ui/input"
import { Download, Printer, ArrowDownRight, ArrowUpRight, ArrowLeftRight, Loader2, Calendar } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { useAccounts } from "@/hooks/use-accounts"
import { useLedger } from "@/hooks/use-reports"

export default function Cashbook() {
  const { accounts: allAccounts, isLoading: isLoadingAccounts } = useAccounts()

  // Filter only cash & bank posting accounts (under 1100 Current Assets, category: false)
  const cashAndBankAccounts = useMemo(() => {
    return (allAccounts || []).filter(
      (acc: any) => !acc.category && (acc.code.startsWith("11") || acc.name.toLowerCase().includes("cash") || acc.name.toLowerCase().includes("bank"))
    )
  }, [allAccounts])

  const [selectedAccount, setSelectedAccount] = useState<string>("")
  const [fromDate, setFromDate] = useState(() => {
    const d = new Date()
    return `${d.getFullYear()}-01-01`
  })
  const [toDate, setToDate] = useState(() => new Date().toISOString().split("T")[0])

  // Automatically select the first cash or bank account when accounts load
  const activeAccountCode = selectedAccount || (cashAndBankAccounts[0]?.code || "1110")
  const activeAccount = cashAndBankAccounts.find((a: any) => a.code === activeAccountCode) || cashAndBankAccounts[0]

  const { data: ledgerData, isLoading: isLoadingLedger } = useLedger(
    activeAccountCode,
    fromDate,
    toDate,
    "PKR"
  )

  const transactions = (ledgerData?.entries || []).map((entry: any, index: number) => {
    const rawType = (entry.voucher_type || "").toLowerCase()
    const ref = (entry.reference || "").toLowerCase()
    const desc = (entry.description || "").toLowerCase()

    let voucherType: "contra" | "opening" | "receipt" | "payment" = "payment"
    if (rawType === "contra" || ref.startsWith("cv") || ref.startsWith("contra") || desc.includes("contra") || desc.includes("internal transfer")) {
      voucherType = "contra"
    } else if (rawType === "opening" || ref.startsWith("ob") || desc.includes("opening balance")) {
      voucherType = "opening"
    } else if (entry.debit > 0) {
      voucherType = "receipt"
    } else {
      voucherType = "payment"
    }

    return {
      id: String(index + 1),
      date: entry.date,
      voucherNo: entry.reference || `VCH-${index + 1}`,
      voucherType,
      particulars: entry.description || (voucherType === "contra" ? "Internal Transfer" : "Ledger transaction"),
      debit: Number(entry.debit) || 0,
      credit: Number(entry.credit) || 0,
      balance: Number(entry.balance) || 0,
    }
  })

  const openingBalance = Number(ledgerData?.opening_balance) || 0
  const totalDebit = Number(ledgerData?.total_debit) || 0
  const totalCredit = Number(ledgerData?.total_credit) || 0
  const closingBalance = Number(ledgerData?.closing_balance) || (openingBalance + totalDebit - totalCredit)

  const handlePrint = () => {
    window.print()
  }

  const handleExportCSV = () => {
    const headers = ["Date", "Voucher No", "Type", "Particulars", "Receipts (Dr)", "Payments (Cr)", "Balance"]
    const rows = transactions.map((t: any) => [
      t.date,
      t.voucherNo,
      t.voucherType.toUpperCase(),
      `"${t.particulars.replace(/"/g, '""')}"`,
      t.debit,
      t.credit,
      t.balance,
    ])
    const csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map((r: any) => r.join(","))].join("\n")
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute("download", `Cashbook_${activeAccountCode}_${fromDate}_to_${toDate}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center flex-wrap gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Cash & Bank Book</h2>
          <p className="text-muted-foreground mt-1">
            Real-time receipts and disbursements statement for cash and bank accounts
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={handleExportCSV} disabled={transactions.length === 0}>
            <Download className="w-4 h-4 mr-2" />
            Export CSV
          </Button>
          <Button variant="outline" onClick={handlePrint}>
            <Printer className="w-4 h-4 mr-2" />
            Print
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label className="text-sm font-medium mb-1 block">Cash / Bank Account</label>
              {isLoadingAccounts ? (
                <div className="h-10 border rounded-md flex items-center px-3 text-sm text-muted-foreground">
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" /> Loading accounts...
                </div>
              ) : (
                <Select value={activeAccountCode} onValueChange={(val) => setSelectedAccount(val)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select account" />
                  </SelectTrigger>
                  <SelectContent>
                    {cashAndBankAccounts.map((acc: any) => (
                      <SelectItem key={acc.code} value={acc.code}>
                        {acc.code} - {acc.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </div>

            <div>
              <label className="text-sm font-medium mb-1 block">From Date</label>
              <Input
                type="date"
                value={fromDate}
                onChange={(e) => setFromDate(e.target.value)}
              />
            </div>

            <div>
              <label className="text-sm font-medium mb-1 block">To Date</label>
              <Input
                type="date"
                value={toDate}
                onChange={(e) => setToDate(e.target.value)}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Opening Balance</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              Rs. {openingBalance.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Inflows (Receipts)</CardTitle>
            <ArrowDownRight className="w-4 h-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600">
              Rs. {totalDebit.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Outflows (Payments)</CardTitle>
            <ArrowUpRight className="w-4 h-4 text-red-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600">
              Rs. {totalCredit.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card className="bg-primary/5 border-primary/20">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-foreground">Closing Balance</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-foreground">
              Rs. {closingBalance.toLocaleString()}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Transactions Table */}
      <Card>
        <CardHeader>
          <CardTitle>
            Transactions — {activeAccount?.name || activeAccountCode} ({activeAccountCode})
          </CardTitle>
          <CardDescription>
            Period from {fromDate} to {toDate}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoadingLedger ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-primary" />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead>Voucher No</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Particulars / Narration</TableHead>
                    <TableHead className="text-right">Receipts (Debit)</TableHead>
                    <TableHead className="text-right">Payments (Credit)</TableHead>
                    <TableHead className="text-right">Running Balance</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {transactions.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-12 text-muted-foreground">
                        No cash or bank transactions recorded for this period.
                      </TableCell>
                    </TableRow>
                  ) : (
                    transactions.map((t: any) => (
                      <TableRow key={t.id}>
                        <TableCell className="font-mono text-xs">{t.date}</TableCell>
                        <TableCell className="font-medium font-mono text-xs">{t.voucherNo}</TableCell>
                        <TableCell>
                          {t.voucherType === "contra" ? (
                            <Badge className="bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-300 dark:border-purple-800 flex items-center gap-1 w-fit">
                              <ArrowLeftRight className="w-3 h-3" />
                              Contra / Transfer
                            </Badge>
                          ) : t.voucherType === "opening" ? (
                            <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-300 dark:border-blue-800 flex items-center gap-1 w-fit">
                              Opening Balance
                            </Badge>
                          ) : t.voucherType === "receipt" ? (
                            <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800 flex items-center gap-1 w-fit">
                              <ArrowDownRight className="w-3 h-3" />
                              Receipt
                            </Badge>
                          ) : (
                            <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-300 dark:border-amber-800 flex items-center gap-1 w-fit">
                              <ArrowUpRight className="w-3 h-3" />
                              Payment
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell>{t.particulars}</TableCell>
                        <TableCell className="text-right font-medium text-green-600">
                          {t.debit > 0 ? `Rs. ${t.debit.toLocaleString()}` : "-"}
                        </TableCell>
                        <TableCell className="text-right font-medium text-red-600">
                          {t.credit > 0 ? `Rs. ${t.credit.toLocaleString()}` : "-"}
                        </TableCell>
                        <TableCell className="text-right font-mono font-semibold">
                          Rs. {t.balance.toLocaleString()}
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
    </div>
  )
}
