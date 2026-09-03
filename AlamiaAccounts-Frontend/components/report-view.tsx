"use client"

import type React from "react"
import { useEffect } from "react"
import { Button } from "@/components/ui/button"
import { X, Printer, Building2, Calendar, FileText } from "lucide-react"

interface ReportViewProps {
  title: string
  children: React.ReactNode
  onClose: () => void
  companyName?: string
  companyCode?: string
  companyAddress?: string
  companyPhone?: string
  companyEmail?: string
  reportDate?: string
  currency?: string
  footerNote?: string
}

export default function ReportView({
  title,
  children,
  onClose,
  companyName,
  companyCode,
  companyAddress,
  companyPhone,
  companyEmail,
  reportDate,
  currency = "PKR",
  footerNote,
}: ReportViewProps) {
  useEffect(() => {
    // Prevent body scroll when modal is open
    document.body.style.overflow = "hidden"
    return () => {
      document.body.style.overflow = "unset"
    }
  }, [])

  const handlePrint = () => {
    window.print()
  }

  const printTimestamp = new Date().toLocaleString("en-PK", {
    dateStyle: "medium",
    timeStyle: "short",
  })

  return (
    <div className="fixed inset-0 bg-background/90 backdrop-blur-xs z-50 overflow-auto">
      <style jsx global>{`
        @media print {
          body * {
            visibility: hidden;
          }
          #report-content,
          #report-content * {
            visibility: visible;
          }
          #report-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            box-shadow: none !important;
            border: none !important;
          }
          .no-print {
            display: none !important;
          }
        }
      `}</style>

      <div className="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">
        {/* Navigation & Action Bar */}
        <div className="no-print mb-6 flex justify-between items-center sticky top-0 bg-background/95 backdrop-blur py-3 z-10 border-b border-border">
          <div className="flex items-center gap-2">
            <FileText className="w-5 h-5 text-primary" />
            <h2 className="text-lg font-semibold text-foreground">{title} — Print Preview</h2>
          </div>
          <div className="flex items-center gap-2">
            <Button onClick={handlePrint} variant="default" size="sm" className="gap-1.5 shadow-xs">
              <Printer className="w-4 h-4" />
              <span>Print / Save PDF</span>
            </Button>
            <Button onClick={onClose} variant="outline" size="sm" className="gap-1.5 bg-background">
              <X className="w-4 h-4" />
              <span>Close</span>
            </Button>
          </div>
        </div>

        {/* Printable Report Document */}
        <div
          id="report-content"
          className="bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100 p-8 sm:p-12 rounded-xl shadow-xl border border-border transition-colors"
        >
          {/* Header Banner */}
          <div className="border-b-2 border-slate-900 dark:border-slate-100 pb-6 mb-8">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
              <div>
                <div className="flex items-center gap-2 text-primary">
                  <Building2 className="w-6 h-6" />
                  <span className="text-xs uppercase font-bold tracking-widest text-muted-foreground">
                    Alamia Accounts • Certified Financial Report
                  </span>
                </div>
                <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1 text-slate-900 dark:text-white">
                  {companyName || "Main Company"}
                </h1>
                {companyAddress && (
                  <p className="text-xs text-slate-600 dark:text-slate-400 mt-1">
                    {companyAddress}
                  </p>
                )}
                {(companyPhone || companyEmail) && (
                  <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {companyPhone && <span>Tel: {companyPhone} </span>}
                    {companyEmail && <span>• Email: {companyEmail}</span>}
                  </p>
                )}
              </div>

              <div className="text-left sm:text-right">
                <span className="inline-block px-3 py-1 bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-200 text-xs font-bold rounded-md uppercase tracking-wider border border-slate-300 dark:border-slate-800">
                  {title}
                </span>
                {reportDate && (
                  <p className="text-xs font-medium text-slate-600 dark:text-slate-400 mt-2 flex items-center sm:justify-end gap-1">
                    <Calendar className="w-3.5 h-3.5" />
                    <span>{reportDate}</span>
                  </p>
                )}
                <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                  Currency: <strong className="font-semibold">{currency}</strong>
                </p>
              </div>
            </div>
          </div>

          {/* Report Body / Tables */}
          <div className="min-h-[400px]">
            {children}
          </div>

          {/* Institutional Footer */}
          <div className="mt-12 pt-6 border-t border-slate-300 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 flex flex-col sm:flex-row justify-between items-center gap-3">
            <div>
              <p className="font-medium text-slate-700 dark:text-slate-300">
                {footerNote || "This is a computer generated document and does not require signature."}
              </p>
              <p className="text-[10px] text-slate-400 mt-0.5">
                Alamia Accounts Double-Entry Engine • GAAP/IFRS Ledger Immutability Enforced
              </p>
            </div>
            <div className="text-right text-[11px]">
              <p>Printed on: {printTimestamp}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
