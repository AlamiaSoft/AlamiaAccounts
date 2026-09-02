"use client"

import { AlertCircle, CheckCircle } from "lucide-react"
import { Card, CardContent } from "@/components/ui/card"

interface VoucherSummaryProps {
  totalDebit: number
  totalCredit: number
  isBalanced: boolean
}

export default function VoucherSummary({ totalDebit, totalCredit, isBalanced }: VoucherSummaryProps) {
  const difference = Math.abs(totalDebit - totalCredit)

  return (
    <Card className="border-border bg-card">
      <CardContent className="pt-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
          <div className="space-y-2">
            <p className="text-sm text-muted-foreground font-medium">Total Debits</p>
            <p className="text-2xl md:text-3xl font-bold text-foreground">
              {totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </p>
          </div>

          <div className="space-y-2">
            <p className="text-sm text-muted-foreground font-medium">Total Credits</p>
            <p className="text-2xl md:text-3xl font-bold text-foreground">
              {totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </p>
          </div>

          <div className="space-y-2">
            <p className="text-sm text-muted-foreground font-medium">Balance Status</p>
            <div
              className={`flex items-center gap-2 text-lg font-semibold ${
                isBalanced ? "text-emerald-600 dark:text-emerald-400" : "text-amber-600 dark:text-amber-400"
              }`}
            >
              {isBalanced ? (
                <>
                  <CheckCircle className="w-5 h-5" />
                  Balanced
                </>
              ) : (
                <>
                  <AlertCircle className="w-5 h-5" />
                  {difference > 0 && `Difference: ${difference.toFixed(2)}`}
                </>
              )}
            </div>
          </div>
        </div>

        {!isBalanced && totalDebit + totalCredit > 0 && (
          <div className="mt-4 p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md">
            <p className="text-sm text-amber-800 dark:text-amber-200">
              ⚠ Voucher must be balanced to post. Current difference: <strong>{difference.toFixed(2)}</strong>
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
