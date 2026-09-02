"use client"

import { AlertCircle, CheckCircle } from "lucide-react"
import { Card, CardContent } from "@/components/ui/card"

interface VoucherSummaryProps {
  totalDebit: number
  totalCredit: number
  isBalanced: boolean
  onAutoBalance?: () => void
}

export default function VoucherSummary({
  totalDebit,
  totalCredit,
  isBalanced,
  onAutoBalance,
}: VoucherSummaryProps) {
  const difference = Math.abs(totalDebit - totalCredit)
  const isDebitShort = totalDebit < totalCredit
  const isCreditShort = totalCredit < totalDebit

  return (
    <Card className="border-border bg-card">
      <CardContent className="pt-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
          <div className="space-y-2">
            <p className="text-sm text-muted-foreground font-medium">Total Debits</p>
            <p className="text-2xl md:text-3xl font-bold text-green-600 dark:text-green-400">
              Rs. {totalDebit.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </p>
          </div>

          <div className="space-y-2">
            <p className="text-sm text-muted-foreground font-medium">Total Credits</p>
            <p className="text-2xl md:text-3xl font-bold text-blue-600 dark:text-blue-400">
              Rs. {totalCredit.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
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
                  <CheckCircle className="w-5 h-5 text-emerald-600" />
                  Balanced ✓
                </>
              ) : (
                <>
                  <AlertCircle className="w-5 h-5 text-amber-600" />
                  {difference > 0 && `Difference: Rs. ${difference.toFixed(2)}`}
                </>
              )}
            </div>
          </div>
        </div>

        {!isBalanced && totalDebit + totalCredit > 0 && (
          <div className="mt-4 p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <p className="text-sm text-amber-800 dark:text-amber-200">
              ⚠ Voucher must be balanced to post.{" "}
              <strong>
                {isDebitShort ? "Debit is short by" : "Credit is short by"} Rs. {difference.toFixed(2)}
              </strong>
            </p>
            {onAutoBalance && (
              <button
                type="button"
                onClick={onAutoBalance}
                className="px-3 py-1.5 text-xs font-semibold rounded-md bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition-colors shrink-0"
              >
                Auto-Balance (Rs. {difference.toFixed(2)})
              </button>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
