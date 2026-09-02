"use client"

import { useEffect } from "react"
import type { Voucher } from "@/lib/sample-data"

interface PrintSettings {
  companyName: string
  companyAddress?: string
  companyPhone?: string
  companyEmail?: string
  logo?: string
  footerNote?: string
  showHeader: boolean
  showFooter: boolean
}

interface VoucherPrintTemplateProps {
  voucher: Voucher
  settings: PrintSettings
  onClose: () => void
}

export default function VoucherPrintTemplate({ voucher, settings, onClose }: VoucherPrintTemplateProps) {
  const totalDebit = voucher.lineItems.reduce((sum, item) => sum + (item.debit || 0), 0)
  const totalCredit = voucher.lineItems.reduce((sum, item) => sum + (item.credit || 0), 0)

  useEffect(() => {
    // Auto-trigger print dialog
    const timer = setTimeout(() => {
      window.print()
    }, 500)

    return () => clearTimeout(timer)
  }, [])

  const getVoucherTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      general: "General Voucher",
      payment: "Payment Voucher",
      receipt: "Receipt Voucher",
      journal: "Journal Voucher",
    }
    return labels[type] || type
  }

  return (
    <div className="fixed inset-0 bg-background z-50 overflow-auto">
      <style jsx global>{`
        @media print {
          body * {
            visibility: hidden;
          }
          #print-content,
          #print-content * {
            visibility: visible;
          }
          #print-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
          }
          .no-print {
            display: none !important;
          }
        }
      `}</style>

      <div className="max-w-4xl mx-auto p-8">
        <div className="no-print mb-4 flex justify-end gap-2">
          <button
            onClick={() => window.print()}
            className="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90"
          >
            Print
          </button>
          <button onClick={onClose} className="px-4 py-2 bg-muted text-foreground rounded-md hover:bg-muted/80">
            Close
          </button>
        </div>

        <div id="print-content" className="bg-white text-black p-12 rounded-lg shadow-lg">
          {/* Header */}
          {settings.showHeader && (
            <div className="border-b-2 border-black pb-6 mb-6">
              <div className="flex items-start justify-between">
                <div>
                  {settings.logo && (
                    <img src={settings.logo || "/placeholder.svg"} alt="Company Logo" className="h-16 mb-4" />
                  )}
                  <h1 className="text-2xl font-bold">{settings.companyName}</h1>
                  {settings.companyAddress && <p className="text-sm mt-1">{settings.companyAddress}</p>}
                  <div className="flex gap-4 text-sm mt-1">
                    {settings.companyPhone && <span>Tel: {settings.companyPhone}</span>}
                    {settings.companyEmail && <span>Email: {settings.companyEmail}</span>}
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-xs text-gray-600 mb-1">Document Type</div>
                  <div className="font-bold text-lg">{getVoucherTypeLabel(voucher.type)}</div>
                </div>
              </div>
            </div>
          )}

          {/* Voucher Details */}
          <div className="mb-8">
            <div className="grid grid-cols-2 gap-6 mb-6">
              <div>
                <div className="text-sm text-gray-600">Voucher Number</div>
                <div className="font-semibold text-lg">{voucher.number}</div>
              </div>
              <div>
                <div className="text-sm text-gray-600">Date</div>
                <div className="font-semibold">
                  {new Date(voucher.date).toLocaleDateString("en-IN", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  })}
                </div>
              </div>
              {voucher.reference && (
                <div>
                  <div className="text-sm text-gray-600">Reference</div>
                  <div className="font-semibold">{voucher.reference}</div>
                </div>
              )}
            </div>

            {voucher.narration && (
              <div className="bg-gray-50 p-4 rounded">
                <div className="text-sm text-gray-600 mb-1">Narration</div>
                <div>{voucher.narration}</div>
              </div>
            )}
          </div>

          {/* Transaction Table */}
          <table className="w-full mb-8">
            <thead>
              <tr className="border-b-2 border-black">
                <th className="text-left py-3 px-2 text-sm font-semibold">Account</th>
                <th className="text-left py-3 px-2 text-sm font-semibold">Description</th>
                <th className="text-right py-3 px-2 text-sm font-semibold">Debit (Rs.)</th>
                <th className="text-right py-3 px-2 text-sm font-semibold">Credit (Rs.)</th>
              </tr>
            </thead>
            <tbody>
              {voucher.lineItems.map((item, index) => (
                <tr key={item.id} className="border-b border-gray-300">
                  <td className="py-3 px-2">
                    <div className="font-medium">{item.accountName}</div>
                    <div className="text-sm text-gray-600">{item.account}</div>
                  </td>
                  <td className="py-3 px-2 text-sm">{item.description || "—"}</td>
                  <td className="py-3 px-2 text-right font-medium">
                    {item.debit > 0 ? item.debit.toLocaleString("en-IN", { minimumFractionDigits: 2 }) : "—"}
                  </td>
                  <td className="py-3 px-2 text-right font-medium">
                    {item.credit > 0 ? item.credit.toLocaleString("en-IN", { minimumFractionDigits: 2 }) : "—"}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="border-t-2 border-black font-bold">
                <td colSpan={2} className="py-4 px-2 text-right">
                  Total:
                </td>
                <td className="py-4 px-2 text-right">
                  Rs.{totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </td>
                <td className="py-4 px-2 text-right">
                  Rs.{totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </td>
              </tr>
            </tfoot>
          </table>

          {/* Signatures */}
          <div className="grid grid-cols-3 gap-8 mt-12 pt-8 border-t border-gray-300">
            <div className="text-center">
              <div className="border-t border-gray-400 pt-2 mt-12">
                <div className="text-sm">Prepared By</div>
              </div>
            </div>
            <div className="text-center">
              <div className="border-t border-gray-400 pt-2 mt-12">
                <div className="text-sm">Checked By</div>
              </div>
            </div>
            <div className="text-center">
              <div className="border-t border-gray-400 pt-2 mt-12">
                <div className="text-sm">Authorized Signatory</div>
              </div>
            </div>
          </div>

          {/* Footer */}
          {settings.showFooter && settings.footerNote && (
            <div className="mt-8 pt-6 border-t border-gray-300 text-center text-sm text-gray-600">
              {settings.footerNote}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
