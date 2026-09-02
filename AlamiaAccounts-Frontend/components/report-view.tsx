"use client"

import type React from "react"

import { useEffect } from "react"
import { Button } from "@/components/ui/button"
import { X, Printer } from "lucide-react"

interface ReportViewProps {
  title: string
  children: React.ReactNode
  onClose: () => void
  companyName?: string
  reportDate?: string
}

export default function ReportView({ title, children, onClose, companyName, reportDate }: ReportViewProps) {
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

  return (
    <div className="fixed inset-0 bg-background z-50 overflow-auto">
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
          }
          .no-print {
            display: none !important;
          }
        }
      `}</style>

      <div className="max-w-5xl mx-auto p-6">
        <div className="no-print mb-4 flex justify-between items-center sticky top-0 bg-background py-2 z-10 border-b">
          <h2 className="text-xl font-semibold">{title}</h2>
          <div className="flex gap-2">
            <Button onClick={handlePrint} variant="default">
              <Printer className="w-4 h-4 mr-2" />
              Print
            </Button>
            <Button onClick={onClose} variant="outline">
              <X className="w-4 h-4 mr-2" />
              Close
            </Button>
          </div>
        </div>

        <div id="report-content" className="bg-white text-black p-8 rounded-lg shadow-lg">
          {/* Header */}
          <div className="text-center mb-8 pb-4 border-b-2 border-black">
            <h1 className="text-2xl font-bold">{companyName || "Company Name"}</h1>
            <h2 className="text-lg font-semibold mt-2">{title}</h2>
            {reportDate && <p className="text-sm mt-1">As at {reportDate}</p>}
          </div>

          {/* Report Content */}
          {children}

          {/* Footer */}
          <div className="mt-8 pt-4 border-t text-center text-sm text-gray-600">
            <p>This is a computer generated report</p>
          </div>
        </div>
      </div>
    </div>
  )
}
