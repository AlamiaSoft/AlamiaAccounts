"use client"

import { useState, useMemo } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Download, Printer, Receipt, Loader2, Calendar } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { useVouchers } from "@/hooks/use-vouchers"

interface FlattenedEntry {
  id: string
  voucherNo: string
  voucherType: string
  date: string
  accountName: string
  debit: number
  credit: number
  narration: string
}

export default function DayBook() {
  const [selectedDate, setSelectedDate] = useState(() => new Date().toISOString().split("T")[0])
  const [showAllDates, setShowAllDates] = useState(false)

  const { vouchers: apiVouchers, isLoading } = useVouchers()

  // Flatten vouchers and line items for the daybook
  const { entries, totalDebit, totalCredit } = useMemo(() => {
    const list: FlattenedEntry[] = []
    let drSum = 0
    let crSum = 0

    const rawVouchers = apiVouchers || []
    const filteredVouchers = showAllDates
      ? rawVouchers
      : rawVouchers.filter((v: any) => v.date?.startsWith(selectedDate))

    for (const v of filteredVouchers) {
      const items = v.line_items || v.details || []
      const vType = v.type || v.voucher_type || "Journal"
      const vNo = v.reference || v.number || `VCH-${v.id}`
      const vNarration = v.description || v.narration || ""

      if (items.length > 0) {
        items.forEach((item: any, idx: number) => {
          const debit = Number(item.debit) || 0
          const credit = Number(item.credit) || 0
          drSum += debit
          crSum += credit

          list.push({
            id: `${v.id || vNo}-${idx}`,
            voucherNo: vNo,
            voucherType: vType,
            date: v.date,
            accountName: item.account_name || item.account || item.account_code || "Unknown Account",
            debit,
            credit,
            narration: item.description || item.memo || vNarration,
          })
        })
      } else {
        // In case voucher has flat amount
        const amt = Number(v.amount) || 0
        drSum += amt
        crSum += amt
        list.push({
          id: String(v.id || vNo),
          voucherNo: vNo,
          voucherType: vType,
          date: v.date,
          accountName: v.account_name || "General Transaction",
          debit: amt,
          credit: 0,
          narration: vNarration,
        })
      }
    }

    return { entries: list, totalDebit: drSum, totalCredit: crSum }
  }, [apiVouchers, selectedDate, showAllDates])

  const handlePrint = () => {
    window.print()
  }

  const handleExportCSV = () => {
    const headers = ["Voucher No", "Type", "Date", "Account", "Debit", "Credit", "Narration"]
    const rows = entries.map((e) => [
      e.voucherNo,
      e.voucherType,
      e.date,
      `"${e.accountName.replace(/"/g, '""')}"`,
      e.debit,
      e.credit,
      `"${e.narration.replace(/"/g, '""')}"`,
    ])
    const csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map((r) => r.join(","))].join("\n")
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute("download", `DayBook_${selectedDate}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center flex-wrap gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Day Book</h2>
          <p className="text-muted-foreground mt-1">
            Chronological register of all financial transactions and journal vouchers posted
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={handleExportCSV} disabled={entries.length === 0}>
            <Download className="w-4 h-4 mr-2" />
            Export CSV
          </Button>
          <Button variant="outline" onClick={handlePrint}>
            <Printer className="w-4 h-4 mr-2" />
            Print
          </Button>
        </div>
      </div>

      {/* Date Filter & Options */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-center gap-4 flex-wrap">
            <div className="w-64">
              <label className="text-sm font-medium mb-1 block">Select Date</label>
              <Input
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
                disabled={showAllDates}
              />
            </div>

            <div className="pt-6">
              <Button
                variant={showAllDates ? "default" : "outline"}
                onClick={() => setShowAllDates(!showAllDates)}
              >
                {showAllDates ? "Filtering by Single Date" : "Show All Recent Dates"}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Debits</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600">
              Rs. {totalDebit.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Credits</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">
              Rs. {totalCredit.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card className={totalDebit === totalCredit ? "bg-green-500/5 border-green-500/20" : "bg-red-500/5 border-red-500/20"}>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Balance Check</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {totalDebit === totalCredit ? (
                <span className="text-green-600 text-lg flex items-center">
                  Balanced (Diff: Rs. 0) ✓
                </span>
              ) : (
                <span className="text-red-600 text-lg">
                  Out of Balance (Diff: Rs. {Math.abs(totalDebit - totalCredit).toLocaleString()})
                </span>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Entries Table */}
      <Card>
        <CardHeader>
          <CardTitle>
            {showAllDates ? "All Recent Vouchers" : `Vouchers on ${selectedDate}`}
          </CardTitle>
          <CardDescription>
            Detailed line items for double-entry transactions
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-primary" />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Voucher No</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Account Name</TableHead>
                    <TableHead className="text-right">Debit</TableHead>
                    <TableHead className="text-right">Credit</TableHead>
                    <TableHead>Narration</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {entries.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-12 text-muted-foreground">
                        No vouchers recorded for this period.
                      </TableCell>
                    </TableRow>
                  ) : (
                    entries.map((entry) => (
                      <TableRow key={entry.id}>
                        <TableCell className="font-mono font-medium text-xs">
                          {entry.voucherNo}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="capitalize">
                            {entry.voucherType}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-mono text-xs text-muted-foreground">
                          {entry.date}
                        </TableCell>
                        <TableCell className="font-medium">{entry.accountName}</TableCell>
                        <TableCell className="text-right font-medium text-green-600">
                          {entry.debit > 0 ? `Rs. ${entry.debit.toLocaleString()}` : "-"}
                        </TableCell>
                        <TableCell className="text-right font-medium text-blue-600">
                          {entry.credit > 0 ? `Rs. ${entry.credit.toLocaleString()}` : "-"}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-xs max-w-xs truncate">
                          {entry.narration || "-"}
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
