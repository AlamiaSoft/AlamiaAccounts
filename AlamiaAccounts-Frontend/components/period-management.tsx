"use client"

import { useState } from "react"
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query"
import { periodApi } from "@/lib/api"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Lock, Unlock, Loader2, Calendar, CheckCircle2 } from "lucide-react"

export default function PeriodManagement() {
  const queryClient = useQueryClient()
  const [selectedYear, setSelectedYear] = useState<number>(() => new Date().getFullYear())
  const [reopenTarget, setReopenTarget] = useState<{ id: number; name: string } | null>(null)
  const [reopenReason, setReopenReason] = useState("")
  const [statusAlert, setStatusAlert] = useState<{ type: "success" | "error"; message: string } | null>(null)

  const { data: periodsResponse, isLoading } = useQuery({
    queryKey: ["periods", selectedYear],
    queryFn: () => periodApi.getAll(selectedYear),
  })

  const periods = periodsResponse?.data?.data || periodsResponse?.data || []

  const closeMutation = useMutation({
    mutationFn: (id: number) => periodApi.close(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ["periods"] })
      setStatusAlert({ type: "success", message: "Accounting period locked successfully. Ordinary postings are now blocked." })
      setTimeout(() => setStatusAlert(null), 5000)
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Failed to close period"
      setStatusAlert({ type: "error", message: msg })
    },
  })

  const reopenMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => periodApi.reopen(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["periods"] })
      setStatusAlert({ type: "success", message: "Accounting period reopened successfully with audit log." })
      setReopenTarget(null)
      setReopenReason("")
      setTimeout(() => setStatusAlert(null), 5000)
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Failed to reopen period"
      setStatusAlert({ type: "error", message: msg })
    },
  })

  const openCount = periods.filter((p: any) => p.status === "open").length
  const closedCount = periods.filter((p: any) => p.status === "closed").length

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Accounting Periods & Fiscal Locking</h2>
          <p className="text-muted-foreground text-sm">
            Control fiscal boundaries and lock periods to prevent accidental backdating or alterations to finalized books.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Calendar className="w-4 h-4 text-muted-foreground" />
          <select
            value={selectedYear}
            onChange={(e) => setSelectedYear(Number(e.target.value))}
            aria-label="Fiscal Year"
            className="h-9 px-3 rounded-md border text-sm font-medium bg-background"
          >
            <option value={2025}>FY 2025</option>
            <option value={2026}>FY 2026</option>
            <option value={2027}>FY 2027</option>
          </select>
        </div>
      </div>

      {statusAlert && (
        <Alert
          variant={statusAlert.type === "error" ? "destructive" : "default"}
          className={statusAlert.type === "success" ? "border-green-500 bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-200" : ""}
        >
          <AlertDescription className="font-medium">{statusAlert.message}</AlertDescription>
        </Alert>
      )}

      {/* Summary Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader className="py-4">
            <CardDescription>Fiscal Year Periods</CardDescription>
            <CardTitle className="text-2xl font-bold">{periods.length}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="py-4">
            <CardDescription>Open Periods (Active Posting)</CardDescription>
            <CardTitle className="text-2xl font-bold text-green-600">{openCount}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="py-4">
            <CardDescription>Locked Periods (Protected)</CardDescription>
            <CardTitle className="text-2xl font-bold text-amber-600">{closedCount}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      {/* Periods Table */}
      <Card>
        <CardHeader>
          <CardTitle>Fiscal Periods for {selectedYear}</CardTitle>
          <CardDescription>Monthly accounting periods and current posting permissions</CardDescription>
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
                    <TableHead>Period</TableHead>
                    <TableHead>Month & Name</TableHead>
                    <TableHead>Date Range</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Lock Details</TableHead>
                    <TableHead className="text-right">Action</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {periods.map((period: any) => (
                    <TableRow key={period.id}>
                      <TableCell className="font-mono text-xs">P-{String(period.period_number).padStart(2, "0")}</TableCell>
                      <TableCell className="font-medium">{period.period_name}</TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground">
                        {period.start_date} to {period.end_date}
                      </TableCell>
                      <TableCell>
                        {period.status === "closed" ? (
                          <Badge variant="destructive" className="flex items-center gap-1 w-fit bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border-red-300">
                            <Lock className="w-3 h-3" /> Locked
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="flex items-center gap-1 w-fit bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 border-green-300">
                            <CheckCircle2 className="w-3 h-3" /> Open
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground max-w-xs truncate">
                        {period.status === "closed" ? (
                          <span>Locked by {period.closed_by || "Accountant"}</span>
                        ) : period.reopened_at ? (
                          <span>Reopened: {period.reopen_reason}</span>
                        ) : (
                          <span>-</span>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        {period.status === "closed" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs gap-1"
                            onClick={() => {
                              setReopenTarget({ id: period.id, name: period.period_name })
                              setReopenReason("")
                            }}
                          >
                            <Unlock className="w-3.5 h-3.5" /> Reopen
                          </Button>
                        ) : (
                          <Button
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs gap-1 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950"
                            disabled={closeMutation.isPending}
                            onClick={() => closeMutation.mutate(period.id)}
                          >
                            <Lock className="w-3.5 h-3.5" /> Lock Period
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Reopen Modal */}
      <Dialog open={!!reopenTarget} onOpenChange={(open) => !open && setReopenTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reopen Accounting Period: {reopenTarget?.name}</DialogTitle>
            <DialogDescription>
              Reopening a closed accounting period requires a documented business reason and will be logged in the permanent audit trail.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <Label htmlFor="reopen-reason">Documented Business Reason *</Label>
            <Textarea
              id="reopen-reason"
              placeholder="e.g. Authorized audit adjustment approved by CFO, late vendor invoice reconciliation..."
              value={reopenReason}
              onChange={(e) => setReopenReason(e.target.value)}
              className="min-h-[80px]"
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setReopenTarget(null)}>
              Cancel
            </Button>
            <Button
              variant="default"
              disabled={!reopenReason.trim() || reopenMutation.isPending}
              onClick={() => {
                if (reopenTarget && reopenReason.trim()) {
                  reopenMutation.mutate({ id: reopenTarget.id, reason: reopenReason.trim() })
                }
              }}
            >
              {reopenMutation.isPending ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : null}
              Confirm Reopen
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
