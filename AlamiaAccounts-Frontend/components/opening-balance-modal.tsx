"use client"

import { useState, useMemo } from "react"
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query"
import { openingBalanceApi } from "@/lib/api"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { CheckCircle2, AlertTriangle, Scale, Loader2 } from "lucide-react"

interface OpeningBalanceModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  accounts: any[]
  onSuccess?: () => void
}

export default function OpeningBalanceModal({
  open,
  onOpenChange,
  accounts,
  onSuccess,
}: OpeningBalanceModalProps) {
  const queryClient = useQueryClient()
  const [balanceDate, setBalanceDate] = useState(() => `${new Date().getFullYear()}-01-01`)
  const [entries, setEntries] = useState<{ [code: string]: { debit: string; credit: string } }>({})
  const [balancingAccount, setBalancingAccount] = useState<string>("5100")
  const [statusAlert, setStatusAlert] = useState<{ type: "success" | "error"; message: string } | null>(null)

  // Fetch current opening balance status
  const { data: statusResponse, isLoading: isLoadingStatus } = useQuery({
    queryKey: ["opening-balance-status"],
    queryFn: () => openingBalanceApi.getStatus(),
    enabled: open,
  })

  const statusData = statusResponse?.data?.data || statusResponse?.data || {}
  const isInitialized = statusData.is_initialized
  const batch = statusData.batch

  // Filter posting leaf accounts
  const leafAccounts = useMemo(() => {
    return (accounts || []).filter((a: any) => !a.category && a.code)
  }, [accounts])

  // Calculate totals
  const { totalDebit, totalCredit, difference } = useMemo(() => {
    let dr = 0
    let cr = 0
    for (const [code, item] of Object.entries(entries)) {
      const d = parseFloat(item.debit) || 0
      const c = parseFloat(item.credit) || 0
      dr += d
      cr += c
    }
    return {
      totalDebit: dr,
      totalCredit: cr,
      difference: Math.round((dr - cr) * 100) / 100,
    }
  }, [entries])

  const postMutation = useMutation({
    mutationFn: (payload: any) => openingBalanceApi.post(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["opening-balance-status"] })
      queryClient.invalidateQueries({ queryKey: ["accounts"] })
      queryClient.invalidateQueries({ queryKey: ["reports"] })
      queryClient.invalidateQueries({ queryKey: ["vouchers"] })
      setStatusAlert({
        type: "success",
        message: "Opening balances successfully posted and verified in the general ledger.",
      })
      if (onSuccess) onSuccess()
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Failed to post opening balances"
      setStatusAlert({ type: "error", message: msg })
    },
  })

  const handleAmountChange = (code: string, side: "debit" | "credit", value: string) => {
    setEntries((prev) => {
      const current = prev[code] || { debit: "", credit: "" }
      return {
        ...prev,
        [code]: {
          debit: side === "debit" ? value : current.debit,
          credit: side === "credit" ? value : current.credit,
        },
      }
    })
  }

  const handlePost = () => {
    const activeEntries: any[] = []
    for (const [code, item] of Object.entries(entries)) {
      const d = parseFloat(item.debit) || 0
      const c = parseFloat(item.credit) || 0
      if (d > 0) {
        activeEntries.push({ account_code: code, amount: d, type: "debit" })
      }
      if (c > 0) {
        activeEntries.push({ account_code: code, amount: c, type: "credit" })
      }
    }

    if (activeEntries.length < 2) {
      setStatusAlert({ type: "error", message: "Please enter at least two accounts with opening balances." })
      return
    }

    postMutation.mutate({
      balance_date: balanceDate,
      entries: activeEntries,
      balancing_account_code: balancingAccount || undefined,
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-4xl max-h-[90vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-xl">
            <Scale className="w-5 h-5 text-primary" />
            Opening Balance Batch Setup
          </DialogTitle>
          <DialogDescription>
            Establish your company's initial balance sheet position. Debits and credits are balanced compoundly across all accounts.
          </DialogDescription>
        </DialogHeader>

        {statusAlert && (
          <Alert
            variant={statusAlert.type === "error" ? "destructive" : "default"}
            className={statusAlert.type === "success" ? "border-green-500 bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-200" : ""}
          >
            <AlertDescription className="font-medium">{statusAlert.message}</AlertDescription>
          </Alert>
        )}

        {isLoadingStatus ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="w-8 h-8 animate-spin text-primary" />
          </div>
        ) : isInitialized ? (
          <div className="space-y-4 py-6">
            <div className="p-4 rounded-lg border bg-muted/40 space-y-2">
              <div className="flex items-center gap-2 text-green-700 dark:text-green-400 font-semibold">
                <CheckCircle2 className="w-5 h-5" />
                Opening Balances Established
              </div>
              <p className="text-sm text-muted-foreground">
                This company's opening position was locked on <strong>{batch?.balance_date}</strong> under voucher reference{" "}
                <strong className="font-mono">{batch?.reference}</strong>.
              </p>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                <div>
                  <span className="text-xs text-muted-foreground">Total Debits</span>
                  <p className="font-semibold">Rs. {Number(batch?.total_debit || 0).toLocaleString()}</p>
                </div>
                <div>
                  <span className="text-xs text-muted-foreground">Total Credits</span>
                  <p className="font-semibold">Rs. {Number(batch?.total_credit || 0).toLocaleString()}</p>
                </div>
                <div>
                  <span className="text-xs text-muted-foreground">Status</span>
                  <p><Badge variant="outline" className="bg-green-100 text-green-800 border-green-300">Certified Posted</Badge></p>
                </div>
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              Note: To maintain double-entry immutability, subsequent modifications must be made via standard Journal Vouchers in Daybook or Voucher Builder.
            </p>
          </div>
        ) : (
          <div className="flex-1 overflow-hidden flex flex-col space-y-4">
            <div className="flex items-center gap-4">
              <div className="space-y-1">
                <Label htmlFor="ob-date">Opening Balance Effective Date</Label>
                <Input
                  id="ob-date"
                  type="date"
                  value={balanceDate}
                  onChange={(e) => setBalanceDate(e.target.value)}
                  className="w-48 h-9"
                />
              </div>
              <div className="flex-1 text-right text-xs text-muted-foreground pt-5">
                Posting accounts available: {leafAccounts.length}
              </div>
            </div>

            {/* Scrollable accounts table */}
            <div className="flex-1 overflow-y-auto border rounded-md max-h-[45vh]">
              <Table>
                <TableHeader className="sticky top-0 bg-muted/80 backdrop-blur z-10">
                  <TableRow>
                    <TableHead className="w-24">Code</TableHead>
                    <TableHead>Account Name</TableHead>
                    <TableHead className="w-36 text-right">Debit (Rs.)</TableHead>
                    <TableHead className="w-36 text-right">Credit (Rs.)</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {leafAccounts.map((acc: any) => {
                    const code = acc.code
                    const item = entries[code] || { debit: "", credit: "" }
                    return (
                      <TableRow key={code}>
                        <TableCell className="font-mono text-xs font-semibold">{code}</TableCell>
                        <TableCell className="text-sm font-medium">{acc.name}</TableCell>
                        <TableCell className="text-right">
                          <Input
                            type="number"
                            min="0"
                            placeholder="0.00"
                            value={item.debit}
                            onChange={(e) => handleAmountChange(code, "debit", e.target.value)}
                            className="h-8 text-right text-xs font-mono"
                          />
                        </TableCell>
                        <TableCell className="text-right">
                          <Input
                            type="number"
                            min="0"
                            placeholder="0.00"
                            value={item.credit}
                            onChange={(e) => handleAmountChange(code, "credit", e.target.value)}
                            className="h-8 text-right text-xs font-mono"
                          />
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>

            {/* Reconciliation Footer Bar */}
            <div className="p-3 border rounded-lg bg-muted/30 space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-6">
                  <div>
                    <span className="text-xs text-muted-foreground block">Total Debits</span>
                    <span className="font-mono font-bold text-green-600">Rs. {totalDebit.toLocaleString()}</span>
                  </div>
                  <div>
                    <span className="text-xs text-muted-foreground block">Total Credits</span>
                    <span className="font-mono font-bold text-blue-600">Rs. {totalCredit.toLocaleString()}</span>
                  </div>
                  <div>
                    <span className="text-xs text-muted-foreground block">Equilibrium Variance</span>
                    <span className="font-mono font-bold">
                      {difference === 0 ? (
                        <span className="text-green-600 flex items-center gap-1">
                          <CheckCircle2 className="w-4 h-4" /> Balanced (Rs. 0)
                        </span>
                      ) : (
                        <span className="text-amber-600 flex items-center gap-1">
                          <AlertTriangle className="w-4 h-4" /> Rs. {Math.abs(difference).toLocaleString()} ({difference > 0 ? "Debit excess" : "Credit excess"})
                        </span>
                      )}
                    </span>
                  </div>
                </div>

                {difference !== 0 && (
                  <div className="flex items-center gap-2">
                    <Label htmlFor="balancing-select" className="text-xs">Allocate difference to:</Label>
                    <select
                      id="balancing-select"
                      aria-label="Allocate difference to"
                      value={balancingAccount}
                      onChange={(e) => setBalancingAccount(e.target.value)}
                      className="h-8 px-2 rounded border text-xs bg-background"
                    >
                      <option value="5100">Owner's Capital (5100)</option>
                      <option value="5200">Retained Earnings (5200)</option>
                    </select>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        <DialogFooter className="mt-4">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
          {!isInitialized && (
            <Button
              onClick={handlePost}
              disabled={postMutation.isPending || (totalDebit === 0 && totalCredit === 0)}
              className="gap-2"
            >
              {postMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
              Post Opening Balances
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
