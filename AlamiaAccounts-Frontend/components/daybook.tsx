"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Download, Printer, Receipt } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import ReportView from "./report-view"

interface DayBookEntry {
  id: string
  voucherNo: string
  voucherType: string
  accountName: string
  debit: number
  credit: number
  narration: string
}

export default function DayBook() {
  const [selectedDate, setSelectedDate] = useState("2024-01-20")
  const [showPrintView, setShowPrintView] = useState(false)

  const entries: DayBookEntry[] = [
    {
      id: "1",
      voucherNo: "RV-002",
      voucherType: "Receipt",
      accountName: "Sales Account",
      debit: 75000,
      credit: 0,
      narration: "Sales receipt from XYZ Corp for invoice #INV-1234",
    },
    {
      id: "2",
      voucherNo: "RV-002",
      voucherType: "Receipt",
      accountName: "Cash in Hand",
      debit: 0,
      credit: 75000,
      narration: "Sales receipt from XYZ Corp for invoice #INV-1234",
    },
    {
      id: "3",
      voucherNo: "PV-002",
      voucherType: "Payment",
      accountName: "Supplier Account",
      debit: 0,
      credit: 30000,
      narration: "Payment to supplier ABC Enterprises",
    },
    {
      id: "4",
      voucherNo: "PV-002",
      voucherType: "Payment",
      accountName: "Bank Account",
      debit: 30000,
      credit: 0,
      narration: "Payment to supplier ABC Enterprises",
    },
    {
      id: "5",
      voucherNo: "JV-001",
      voucherType: "Journal",
      accountName: "Depreciation Expense",
      debit: 5000,
      credit: 0,
      narration: "Monthly depreciation on fixed assets",
    },
    {
      id: "6",
      voucherNo: "JV-001",
      voucherType: "Journal",
      accountName: "Accumulated Depreciation",
      debit: 0,
      credit: 5000,
      narration: "Monthly depreciation on fixed assets",
    },
  ]

  const totalDebit = entries.reduce((sum, e) => sum + e.debit, 0)
  const totalCredit = entries.reduce((sum, e) => sum + e.credit, 0)

  const voucherTypes = ["Receipt", "Payment", "Journal", "Contra", "Sales", "Purchase"]

  const PrintContent = () => (
    <div>
      <div className="mb-6 grid grid-cols-3 gap-4 text-sm">
        <div>
          <span className="font-semibold">Total Vouchers:</span> 3
        </div>
        <div>
          <span className="font-semibold">Total Debit:</span> Rs.
          {totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
        </div>
        <div>
          <span className="font-semibold">Total Credit:</span> Rs.
          {totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
        </div>
      </div>

      <table className="w-full border-collapse border border-gray-300">
        <thead>
          <tr className="bg-gray-100">
            <th className="border border-gray-300 px-4 py-2 text-left">Voucher No.</th>
            <th className="border border-gray-300 px-4 py-2 text-left">Type</th>
            <th className="border border-gray-300 px-4 py-2 text-left">Account Name</th>
            <th className="border border-gray-300 px-4 py-2 text-right">Debit</th>
            <th className="border border-gray-300 px-4 py-2 text-right">Credit</th>
            <th className="border border-gray-300 px-4 py-2 text-left">Narration</th>
          </tr>
        </thead>
        <tbody>
          {entries.map((entry) => (
            <tr key={entry.id}>
              <td className="border border-gray-300 px-4 py-2 font-mono text-sm">{entry.voucherNo}</td>
              <td className="border border-gray-300 px-4 py-2">{entry.voucherType}</td>
              <td className="border border-gray-300 px-4 py-2 font-medium">{entry.accountName}</td>
              <td className="border border-gray-300 px-4 py-2 text-right">
                {entry.debit > 0 ? `Rs.${entry.debit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}` : "-"}
              </td>
              <td className="border border-gray-300 px-4 py-2 text-right">
                {entry.credit > 0 ? `Rs.${entry.credit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}` : "-"}
              </td>
              <td className="border border-gray-300 px-4 py-2 text-sm">{entry.narration}</td>
            </tr>
          ))}
          <tr className="bg-gray-100 font-bold">
            <td colSpan={3} className="border border-gray-300 px-4 py-2">
              Total
            </td>
            <td className="border border-gray-300 px-4 py-2 text-right">
              Rs.{totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </td>
            <td className="border border-gray-300 px-4 py-2 text-right">
              Rs.{totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </td>
            <td className="border border-gray-300 px-4 py-2" />
          </tr>
        </tbody>
      </table>
    </div>
  )

  if (showPrintView) {
    return (
      <ReportView
        title="Day Book"
        onClose={() => setShowPrintView(false)}
        companyName="Acme Corporation"
        reportDate={new Date(selectedDate).toLocaleDateString("en-IN")}
      >
        <PrintContent />
      </ReportView>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-3xl font-bold">Day Book</h2>
        <p className="text-muted-foreground mt-1">Chronological record of all daily transactions</p>
      </div>

      <div className="flex flex-wrap gap-4">
        <div className="flex items-center gap-2">
          <label className="text-sm font-medium">Date:</label>
          <input
            type="date"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            className="px-3 py-2 border rounded-md text-sm"
          />
        </div>

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
            <CardDescription>Total Vouchers</CardDescription>
            <CardTitle className="text-2xl">3</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Debit</CardDescription>
            <CardTitle className="text-2xl">
              Rs.{totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Credit</CardDescription>
            <CardTitle className="text-2xl">
              Rs.{totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
            </CardTitle>
          </CardHeader>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Daily Transactions</CardTitle>
          <CardDescription>All transactions for {new Date(selectedDate).toLocaleDateString("en-IN")}</CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Voucher No.</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Account Name</TableHead>
                <TableHead className="text-right">Debit</TableHead>
                <TableHead className="text-right">Credit</TableHead>
                <TableHead>Narration</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {entries.map((entry, i) => (
                <TableRow key={entry.id || entry.voucherNo || `entry-${i}`}>
                  <TableCell className="font-mono text-sm">{entry.voucherNo}</TableCell>
                  <TableCell>
                    <Badge variant="outline">
                      <Receipt className="w-3 h-3 mr-1" />
                      {entry.voucherType}
                    </Badge>
                  </TableCell>
                  <TableCell className="font-medium">{entry.accountName}</TableCell>
                  <TableCell className="text-right font-medium">
                    {entry.debit > 0 ? `Rs.${entry.debit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}` : "-"}
                  </TableCell>
                  <TableCell className="text-right font-medium">
                    {entry.credit > 0 ? `Rs.${entry.credit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}` : "-"}
                  </TableCell>
                  <TableCell className="text-sm text-muted-foreground max-w-xs truncate">{entry.narration}</TableCell>
                </TableRow>
              ))}
              <TableRow className="bg-muted/50 font-bold">
                <TableCell colSpan={3}>Total</TableCell>
                <TableCell className="text-right">
                  Rs.{totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </TableCell>
                <TableCell className="text-right">
                  Rs.{totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </TableCell>
                <TableCell />
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
