"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Download, Printer, ArrowDownRight, ArrowUpRight } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import ReportView from "./report-view"

interface CashTransaction {
  id: string
  date: string
  voucherNo: string
  voucherType: "receipt" | "payment"
  particulars: string
  debit: number
  credit: number
  balance: number
}

export default function Cashbook() {
  const [selectedAccount, setSelectedAccount] = useState("cash-in-hand")
  const [dateRange, setDateRange] = useState("this-month")
  const [showPrintView, setShowPrintView] = useState(false)

  const transactions: CashTransaction[] = [
    {
      id: "1",
      date: "2024-01-15",
      voucherNo: "RV-001",
      voucherType: "receipt",
      particulars: "Sales Receipt from ABC Ltd",
      debit: 50000,
      credit: 0,
      balance: 50000,
    },
    {
      id: "2",
      date: "2024-01-16",
      voucherNo: "PV-001",
      voucherType: "payment",
      particulars: "Rent Payment",
      debit: 0,
      credit: 15000,
      balance: 35000,
    },
    {
      id: "3",
      date: "2024-01-18",
      voucherNo: "RV-002",
      voucherType: "receipt",
      particulars: "Sales Receipt from XYZ Corp",
      debit: 75000,
      credit: 0,
      balance: 110000,
    },
    {
      id: "4",
      date: "2024-01-20",
      voucherNo: "PV-002",
      voucherType: "payment",
      particulars: "Supplier Payment",
      debit: 0,
      credit: 30000,
      balance: 80000,
    },
  ]

  const totalDebit = transactions.reduce((sum, t) => sum + t.debit, 0)
  const totalCredit = transactions.reduce((sum, t) => sum + t.credit, 0)
  const closingBalance = totalDebit - totalCredit

  const PrintContent = () => (
    <div>
      <div className="mb-6 grid grid-cols-3 gap-4 text-sm">
        <div>
          <span className="font-semibold">Account:</span> {selectedAccount.replace("-", " ").toUpperCase()}
        </div>
        <div>
          <span className="font-semibold">Period:</span> {dateRange.replace("-", " ").toUpperCase()}
        </div>
        <div>
          <span className="font-semibold">Closing Balance:</span> Rs.
          {closingBalance.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
        </div>
      </div>

      <table className="w-full border-collapse border border-gray-300">
        <thead>
          <tr className="bg-gray-100">
            <th className="border border-gray-300 px-4 py-2 text-left">Date</th>
            <th className="border border-gray-300 px-4 py-2 text-left">Voucher No.</th>
            <th className="border border-gray-300 px-4 py-2 text-left">Type</th>
            <th className="border border-gray-300 px-4 py-2 text-left">Particulars</th>
            <th className="border border-gray-300 px-4 py-2 text-right">Receipt (Dr)</th>
            <th className="border border-gray-300 px-4 py-2 text-right">Payment (Cr)</th>
            <th className="border border-gray-300 px-4 py-2 text-right">Balance</th>
          </tr>
        </thead>
        <tbody>
          <tr className="bg-gray-50">
            <td colSpan={4} className="border border-gray-300 px-4 py-2 font-medium">
              Opening Balance
            </td>
            <td className="border border-gray-300 px-4 py-2" />
            <td className="border border-gray-300 px-4 py-2" />
            <td className="border border-gray-300 px-4 py-2 text-right font-medium">Rs.0.00</td>
          </tr>
          {transactions.map((transaction) => (
            <tr key={transaction.id}>
              <td className="border border-gray-300 px-4 py-2">
                {new Date(transaction.date).toLocaleDateString("en-IN")}
              </td>
              <td className="border border-gray-300 px-4 py-2 font-mono text-sm">{transaction.voucherNo}</td>
              <td className="border border-gray-300 px-4 py-2">
                {transaction.voucherType === "receipt" ? "Receipt" : "Payment"}
              </td>
              <td className="border border-gray-300 px-4 py-2">{transaction.particulars}</td>
              <td className="border border-gray-300 px-4 py-2 text-right">
                {transaction.debit > 0
                  ? `Rs.${transaction.debit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}`
                  : "-"}
              </td>
              <td className="border border-gray-300 px-4 py-2 text-right">
                {transaction.credit > 0
                  ? `Rs.${transaction.credit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}`
                  : "-"}
              </td>
              <td className="border border-gray-300 px-4 py-2 text-right font-medium">
                Rs.{transaction.balance.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
              </td>
            </tr>
          ))}
          <tr className="bg-gray-100 font-bold">
            <td colSpan={4} className="border border-gray-300 px-4 py-2">
              Closing Balance
            </td>
            <td className="border border-gray-300 px-4 py-2 text-right">
              Rs.{totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </td>
            <td className="border border-gray-300 px-4 py-2 text-right">
              Rs.{totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </td>
            <td className="border border-gray-300 px-4 py-2 text-right">
              Rs.{closingBalance.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  )

  if (showPrintView) {
    return (
      <ReportView
        title="Cashbook"
        onClose={() => setShowPrintView(false)}
        companyName="Acme Corporation"
        reportDate={new Date().toLocaleDateString("en-IN")}
      >
        <PrintContent />
      </ReportView>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-3xl font-bold">Cashbook</h2>
        <p className="text-muted-foreground mt-1">Track all cash receipts and payments</p>
      </div>

      <div className="flex flex-wrap gap-4">
        <Select value={selectedAccount} onValueChange={setSelectedAccount}>
          <SelectTrigger className="w-[240px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="cash-in-hand">Cash in Hand</SelectItem>
            <SelectItem value="petty-cash">Petty Cash</SelectItem>
            <SelectItem value="cash-drawer">Cash Drawer</SelectItem>
          </SelectContent>
        </Select>

        <Select value={dateRange} onValueChange={setDateRange}>
          <SelectTrigger className="w-[200px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="today">Today</SelectItem>
            <SelectItem value="this-week">This Week</SelectItem>
            <SelectItem value="this-month">This Month</SelectItem>
            <SelectItem value="this-quarter">This Quarter</SelectItem>
            <SelectItem value="this-year">This Year</SelectItem>
            <SelectItem value="custom">Custom Range</SelectItem>
          </SelectContent>
        </Select>

        <div className="flex-1" />

        <Button variant="outline" onClick={() => setShowPrintView(true)}>
          <Printer className="w-4 h-4 mr-2" />
          Print
        </Button>
        <Button variant="outline">
          <Download className="w-4 h-4 mr-2" />
          Export
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Opening Balance</CardDescription>
            <CardTitle className="text-2xl">Rs.0.00</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Receipts</CardDescription>
            <CardTitle className="text-2xl text-green-600">
              Rs.{totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Payments</CardDescription>
            <CardTitle className="text-2xl text-red-600">
              Rs.{totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </CardTitle>
          </CardHeader>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Cash Transactions</CardTitle>
          <CardDescription>All receipts and payments for selected period</CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Date</TableHead>
                <TableHead>Voucher No.</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Particulars</TableHead>
                <TableHead className="text-right">Receipt (Dr)</TableHead>
                <TableHead className="text-right">Payment (Cr)</TableHead>
                <TableHead className="text-right">Balance</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow className="bg-muted/50">
                <TableCell colSpan={4} className="font-medium">
                  Opening Balance
                </TableCell>
                <TableCell />
                <TableCell />
                <TableCell className="text-right font-medium">Rs.0.00</TableCell>
              </TableRow>
              {transactions.map((transaction, i) => (
                <TableRow key={transaction.id || transaction.voucherNo || `tx-${i}`}>
                  <TableCell>{new Date(transaction.date).toLocaleDateString("en-IN")}</TableCell>
                  <TableCell className="font-mono text-sm">{transaction.voucherNo}</TableCell>
                  <TableCell>
                    <Badge variant={transaction.voucherType === "receipt" ? "default" : "secondary"}>
                      {transaction.voucherType === "receipt" ? (
                        <ArrowDownRight className="w-3 h-3 mr-1" />
                      ) : (
                        <ArrowUpRight className="w-3 h-3 mr-1" />
                      )}
                      {transaction.voucherType === "receipt" ? "Receipt" : "Payment"}
                    </Badge>
                  </TableCell>
                  <TableCell>{transaction.particulars}</TableCell>
                  <TableCell className="text-right font-medium text-green-600">
                    {transaction.debit > 0
                      ? `Rs.${transaction.debit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}`
                      : "-"}
                  </TableCell>
                  <TableCell className="text-right font-medium text-red-600">
                    {transaction.credit > 0
                      ? `Rs.${transaction.credit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}`
                      : "-"}
                  </TableCell>
                  <TableCell className="text-right font-medium">
                    Rs.{transaction.balance.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                  </TableCell>
                </TableRow>
              ))}
              <TableRow className="bg-muted/50 font-bold">
                <TableCell colSpan={4}>Closing Balance</TableCell>
                <TableCell className="text-right text-green-600">
                  Rs.{totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </TableCell>
                <TableCell className="text-right text-red-600">
                  Rs.{totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </TableCell>
                <TableCell className="text-right">
                  Rs.{closingBalance.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
