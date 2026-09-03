"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { ArrowLeft, FileText, Edit, Trash2, Printer } from "lucide-react"
import type { Voucher } from "@/lib/sample-data"
import VoucherPrintTemplate from "./voucher-print-template"
import type { PrintSettings } from "./print-template-settings"

interface VoucherViewProps {
  voucher: Voucher
  onBack: () => void
  onEdit?: () => void
  onDelete?: () => void
  printSettings?: PrintSettings
}

export default function VoucherView({ voucher, onBack, onEdit, onDelete, printSettings }: VoucherViewProps) {
  const [showPrint, setShowPrint] = useState(false)

  const totalDebit = voucher.lineItems.reduce((sum, item) => sum + (item.debit || 0), 0)
  const totalCredit = voucher.lineItems.reduce((sum, item) => sum + (item.credit || 0), 0)

  const defaultPrintSettings: PrintSettings = printSettings || {
    companyName: "Main Company",
    companyAddress: "Head Office, Alamia Complex, Islamabad, Pakistan",
    companyPhone: "+92 51 111 252 642",
    companyEmail: "accounts@alamiaconnect.com",
    footerNote: "This is a computer generated document and does not require signature.",
    showHeader: true,
    showFooter: true,
  }

  const getVoucherTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      general: "General Voucher",
      payment: "Payment Voucher",
      receipt: "Receipt Voucher",
      journal: "Journal Voucher",
    }
    return labels[type] || type
  }

  const getVoucherTypeBadge = (type: string) => {
    const variants: Record<string, "default" | "secondary" | "destructive" | "outline"> = {
      general: "default",
      payment: "destructive",
      receipt: "secondary",
      journal: "outline",
    }
    return variants[type] || "default"
  }

  const handlePrint = () => {
    setShowPrint(true)
  }

  if (showPrint) {
    return (
      <VoucherPrintTemplate voucher={voucher} settings={defaultPrintSettings} onClose={() => setShowPrint(false)} />
    )
  }

  return (
    <div className="w-full max-w-6xl mx-auto p-4 md:p-6 lg:p-8">
      {/* Header */}
      <div className="mb-8">
        <Button variant="ghost" onClick={onBack} className="mb-4 -ml-2">
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to List
        </Button>

        <div className="flex items-start justify-between">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <FileText className="w-8 h-8 text-primary" />
              <h1 className="text-3xl font-bold text-foreground">{voucher.number}</h1>
              <Badge variant={getVoucherTypeBadge(voucher.type)}>{getVoucherTypeLabel(voucher.type)}</Badge>
            </div>
            <p className="text-muted-foreground">{voucher.narration}</p>
          </div>

          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={handlePrint}>
              <Printer className="w-4 h-4 mr-2" />
              Print
            </Button>
            {onEdit && (
              <Button variant="outline" size="sm" onClick={onEdit}>
                <Edit className="w-4 h-4 mr-2" />
                Edit
              </Button>
            )}
            {onDelete && (
              <Button variant="outline" size="sm" onClick={onDelete}>
                <Trash2 className="w-4 h-4 mr-2" />
                Delete
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* Voucher Details */}
      <div className="space-y-6">
        <Card className="border-border">
          <CardHeader className="pb-4">
            <CardTitle className="text-lg">Voucher Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <p className="text-sm text-muted-foreground mb-1">Voucher Number</p>
                <p className="font-medium">{voucher.number}</p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground mb-1">Date</p>
                <p className="font-medium">
                  {new Date(voucher.date).toLocaleDateString("en-IN", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  })}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground mb-1">Reference</p>
                <p className="font-medium">{voucher.reference || "—"}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Line Items */}
        <Card className="border-border">
          <CardHeader className="pb-4">
            <CardTitle className="text-lg">Transaction Details</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">Account</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">Description</th>
                    <th className="text-right py-3 px-4 text-sm font-medium text-muted-foreground">Debit (Rs.)</th>
                    <th className="text-right py-3 px-4 text-sm font-medium text-muted-foreground">Credit (Rs.)</th>
                  </tr>
                </thead>
                <tbody>
                  {voucher.lineItems.map((item, index) => (
                    <tr
                      key={item.id}
                      className={index !== voucher.lineItems.length - 1 ? "border-b border-border" : ""}
                    >
                      <td className="py-4 px-4">
                        <div>
                          <p className="font-medium">{item.accountName}</p>
                          <p className="text-sm text-muted-foreground">{item.account}</p>
                        </div>
                      </td>
                      <td className="py-4 px-4 text-muted-foreground">{item.description || "—"}</td>
                      <td className="py-4 px-4 text-right font-medium">
                        {item.debit > 0 ? item.debit.toLocaleString("en-IN") : "—"}
                      </td>
                      <td className="py-4 px-4 text-right font-medium">
                        {item.credit > 0 ? item.credit.toLocaleString("en-IN") : "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t-2 border-border font-bold">
                    <td colSpan={2} className="py-4 px-4 text-right">
                      Total:
                    </td>
                    <td className="py-4 px-4 text-right">Rs.{totalDebit.toLocaleString("en-IN")}</td>
                    <td className="py-4 px-4 text-right">Rs.{totalCredit.toLocaleString("en-IN")}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </CardContent>
        </Card>

        {/* Narration */}
        {voucher.narration && (
          <Card className="border-border">
            <CardHeader className="pb-4">
              <CardTitle className="text-lg">Narration</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-foreground">{voucher.narration}</p>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}
