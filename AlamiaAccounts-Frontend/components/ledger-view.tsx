"use client"

import { useState, useEffect } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Loader2, Calendar } from "lucide-react"
import { useAccounts } from "@/hooks/use-accounts"
import { useLedger } from "@/hooks/use-reports"

interface Transaction {
  date: string
  description: string
  debit: number
  credit: number
  balance: number
  voucherNo: string
}

export default function LedgerView() {
  const [selectedAccount, setSelectedAccount] = useState("")
  const [fromDate, setFromDate] = useState("2024-01-01")
  const [toDate, setToDate] = useState(new Date().toISOString().split("T")[0])
  const [filterType, setFilterType] = useState("all")

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

  const accounts = (apiAccounts || []).map((acc: any) => ({
    id: acc.code,
    name: acc.name,
    type: acc.category
  }))

  const transactions: Transaction[] = (ledgerData?.entries || []).map((entry: any) => ({
    date: entry.date,
    description: entry.description,
    debit: entry.debit,
    credit: entry.credit,
    balance: entry.balance,
    voucherNo: entry.reference
  }))

  const openingBalance = ledgerData?.opening_balance || 0
  const totalDebit = ledgerData?.total_debit || 0
  const totalCredit = ledgerData?.total_credit || 0
  const closingBalance = ledgerData?.closing_balance || 0

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-3xl font-bold tracking-tight">General Ledger</h2>
        <p className="text-muted-foreground mt-1">View and analyze account transactions</p>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="grid gap-4 md:grid-cols-4">
            <div>
              <label className="block text-sm font-medium mb-2">Account</label>
              <Select value={selectedAccount} onValueChange={setSelectedAccount}>
                <SelectTrigger>
                  <SelectValue placeholder={isLoadingAccounts ? "Loading..." : "Select Account"} />
                </SelectTrigger>
                <SelectContent>
                  {isLoadingAccounts ? (
                    <div className="flex items-center justify-center p-2">
                      <Loader2 className="w-4 h-4 animate-spin" />
                    </div>
                  ) : (
                    accounts.map((acc) => (
                      <SelectItem key={acc.id} value={acc.id}>
                        {acc.name} ({acc.id})
                      </SelectItem>
                    ))
                  )}
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">From Date</label>
              <div className="relative">
                <input
                  type="date"
                  value={fromDate}
                  onChange={(e) => setFromDate(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg bg-background"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">To Date</label>
              <div className="relative">
                <input
                  type="date"
                  value={toDate}
                  onChange={(e) => setToDate(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg bg-background"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">Transaction Type</label>
              <Select value={filterType} onValueChange={setFilterType}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Transactions</SelectItem>
                  <SelectItem value="debit">Debit Only</SelectItem>
                  <SelectItem value="credit">Credit Only</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Account Summary */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardContent className="pt-6">
            <p className="text-sm text-muted-foreground mb-1">Opening Balance</p>
            <p className="text-2xl font-bold">Rs.{openingBalance.toLocaleString()}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <p className="text-sm text-muted-foreground mb-1">Total Debit / Credit</p>
            <p className="text-2xl font-bold">
              Rs.{totalDebit.toLocaleString()} / Rs.{totalCredit.toLocaleString()}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <p className="text-sm text-muted-foreground mb-1">Closing Balance</p>
            <p className="text-2xl font-bold text-green-600">Rs.{closingBalance.toLocaleString()}</p>
          </CardContent>
        </Card>
      </div>

      {/* Transactions Table */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Account Transactions</CardTitle>
            {isLoadingLedger && <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />}
          </div>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-3 px-4 font-semibold">Date</th>
                  <th className="text-left py-3 px-4 font-semibold">Description</th>
                  <th className="text-left py-3 px-4 font-semibold">Voucher No</th>
                  <th className="text-right py-3 px-4 font-semibold">Debit</th>
                  <th className="text-right py-3 px-4 font-semibold">Credit</th>
                  <th className="text-right py-3 px-4 font-semibold">Balance</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((transaction, index) => (
                  <tr key={index} className="border-b hover:bg-muted/50 transition-colors">
                    <td className="py-3 px-4">{transaction.date}</td>
                    <td className="py-3 px-4">{transaction.description}</td>
                    <td className="py-3 px-4 text-muted-foreground">{transaction.voucherNo}</td>
                    <td className="text-right py-3 px-4 text-green-600 font-medium">
                      {transaction.debit > 0 ? `Rs.${transaction.debit}` : "-"}
                    </td>
                    <td className="text-right py-3 px-4 text-red-600 font-medium">
                      {transaction.credit > 0 ? `Rs.${transaction.credit}` : "-"}
                    </td>
                    <td className="text-right py-3 px-4 font-medium">Rs.{transaction.balance.toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
