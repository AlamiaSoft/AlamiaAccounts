"use client"

import { ArrowLeft, Download, Filter, Loader2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { useState } from "react"
import { useLedger } from "@/hooks/use-reports"

interface LedgerTransaction {
  id: string
  date: string
  voucherType: string
  voucherNumber: string
  particulars: string
  debit: number
  credit: number
  balance: number
}

interface LedgerDetailViewProps {
  accountName: string
  accountCode: string
  onBack: () => void
}

// No longer using static SAMPLE_TRANSACTIONS

export default function LedgerDetailView({ accountName, accountCode, onBack }: LedgerDetailViewProps) {
  const [fromDate, setFromDate] = useState("2024-01-01")
  const [toDate, setToDate] = useState(new Date().toISOString().split("T")[0])
  const [showFilters, setShowFilters] = useState(false)

  const { data: ledgerData, isLoading } = useLedger(accountCode, fromDate, toDate, "PKR")

  const transactions = (ledgerData?.entries || []).map((entry: any, index: number) => ({
    id: index.toString(),
    date: entry.date,
    voucherType: entry.reference?.split("-")[0] || "JV",
    voucherNumber: entry.reference,
    particulars: entry.description,
    debit: entry.debit,
    credit: entry.credit,
    balance: entry.balance,
  }))

  const totalDebit = ledgerData?.total_debit || 0
  const totalCredit = ledgerData?.total_credit || 0
  const closingBalance = ledgerData?.closing_balance || 0
  const openingBalance = ledgerData?.opening_balance || 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={onBack}>
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back
          </Button>
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Ledger: {accountName}</h1>
            <p className="text-muted-foreground mt-1">Account Code: {accountCode}</p>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setShowFilters(!showFilters)}>
            <Filter className="w-4 h-4 mr-2" />
            Filter
          </Button>
          <Button variant="outline">
            <Download className="w-4 h-4 mr-2" />
            Export
          </Button>
        </div>
      </div>

      {showFilters && (
        <Card>
          <CardContent className="pt-6">
            <div className="flex flex-col md:flex-row gap-4 items-end">
              <div className="flex-1">
                <label className="block text-sm font-medium mb-2">From Date</label>
                <input
                  type="date"
                  value={fromDate}
                  onChange={(e) => setFromDate(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg bg-background"
                />
              </div>
              <div className="flex-1">
                <label className="block text-sm font-medium mb-2">To Date</label>
                <input
                  type="date"
                  value={toDate}
                  onChange={(e) => setToDate(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg bg-background"
                />
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-3">
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
            <p className="text-2xl font-bold">Rs. {closingBalance.toLocaleString("en-IN")}</p>
          </CardContent>
        </Card>
      </div>

      {/* Transactions Table */}
      <Card>
        <CardHeader>
          <CardTitle>Transaction History</CardTitle>
          <CardDescription>
            {isLoading ? (
              <span className="flex items-center">
                <Loader2 className="w-3 h-3 animate-spin mr-2" /> Loading ledger...
              </span>
            ) : (
              `Opening Balance: Rs. ${openingBalance.toLocaleString("en-IN")}`
            )}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-3 px-2 font-semibold">Date</th>
                  <th className="text-left py-3 px-2 font-semibold">Voucher</th>
                  <th className="text-left py-3 px-2 font-semibold">Particulars</th>
                  <th className="text-right py-3 px-2 font-semibold">Debit</th>
                  <th className="text-right py-3 px-2 font-semibold">Credit</th>
                  <th className="text-right py-3 px-2 font-semibold">Balance</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((transaction: any) => (
                  <tr key={transaction.id} className="border-b hover:bg-muted/50">
                    <td className="py-3 px-2">{new Date(transaction.date).toLocaleDateString("en-IN")}</td>
                    <td className="py-3 px-2">
                      <Badge variant="outline" className="text-xs">
                        {transaction.voucherType}
                      </Badge>
                      <p className="text-xs text-muted-foreground mt-1">{transaction.voucherNumber}</p>
                    </td>
                    <td className="py-3 px-2">{transaction.particulars}</td>
                    <td className="py-3 px-2 text-right text-red-600 font-medium">
                      {transaction.debit > 0 ? transaction.debit.toLocaleString("en-IN") : "-"}
                    </td>
                    <td className="py-3 px-2 text-right text-green-600 font-medium">
                      {transaction.credit > 0 ? transaction.credit.toLocaleString("en-IN") : "-"}
                    </td>
                    <td className="py-3 px-2 text-right font-medium">{transaction.balance.toLocaleString("en-IN")}</td>
                  </tr>
                ))}
                <tr className="border-t-2 font-bold">
                  <td className="py-3 px-2" colSpan={3}>
                    Total
                  </td>
                  <td className="py-3 px-2 text-right text-red-600">{totalDebit.toLocaleString("en-IN")}</td>
                  <td className="py-3 px-2 text-right text-green-600">{totalCredit.toLocaleString("en-IN")}</td>
                  <td className="py-3 px-2 text-right">{closingBalance.toLocaleString("en-IN")}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
