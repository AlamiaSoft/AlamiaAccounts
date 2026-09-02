"use client"

import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from "recharts"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { TrendingUp, TrendingDown, Wallet, BookOpen, Clock, AlertCircle } from "lucide-react"
import { useBalanceSheet, useProfitLoss } from "@/hooks/use-reports"
import { useVouchers } from "@/hooks/use-vouchers"
import { cn } from "@/lib/utils"

const COLORS = ["#7c3aed", "#f97316", "#06b6d4"]

const StatCard = ({
  title,
  value,
  subtitle,
  icon: Icon,
  variant = "default",
}: {
  title: string
  value: string
  subtitle?: string
  icon: any
  variant?: "default" | "success" | "warning"
}) => (
  <Card>
    <CardContent className="pt-6">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-muted-foreground mb-1">{title}</p>
          <p className="text-2xl font-bold tracking-tight">{value}</p>
        </div>
        <div
          className={cn(
            "p-3 rounded-lg",
            variant === "success"
              ? "bg-green-100 dark:bg-green-900/30 text-green-600"
              : variant === "warning"
              ? "bg-amber-100 dark:bg-amber-900/30 text-amber-600"
              : "bg-primary/10 text-primary",
          )}
        >
          <Icon className="w-5 h-5" />
        </div>
      </div>
      {subtitle && <p className="text-xs text-muted-foreground mt-2">{subtitle}</p>}
    </CardContent>
  </Card>
)

export default function Dashboard() {
  const today = new Date().toISOString().split("T")[0]
  const currentYear = new Date().getFullYear()
  const startOfYear = `${currentYear}-01-01`

  const { data: bsData, isLoading: isLoadingBS } = useBalanceSheet(today, "PKR")
  const { data: plData, isLoading: isLoadingPL } = useProfitLoss(startOfYear, today, "PKR")
  const { data: vouchers, isLoading: isLoadingVouchers } = useVouchers()

  const totalAssets = Number(bsData?.total_assets) || 0
  const totalLiabilities = Number(bsData?.total_liabilities) || 0
  const totalEquity = Number(bsData?.total_equity) || 0
  const netIncome = Number(plData?.net_profit) || 0
  const totalRevenue = Number(plData?.total_income ?? plData?.total_revenue) || 0
  const totalExpenses = Number(plData?.total_expenses) || 0

  const hasFinancialData = totalAssets > 0 || totalLiabilities > 0 || totalRevenue > 0 || totalExpenses > 0

  // Real Account Distribution
  const accountDistribution = [
    { name: "Assets", value: totalAssets },
    { name: "Liabilities", value: totalLiabilities },
    { name: "Equity", value: totalEquity },
  ].filter((item) => item.value > 0)

  // Real Income vs Expense Data
  const performanceData = [
    {
      category: "YTD Performance",
      Revenue: totalRevenue,
      Expenses: totalExpenses,
    },
  ]

  const recentVouchers = Array.isArray(vouchers) ? vouchers.slice(0, 5) : []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-3xl font-bold tracking-tight">Financial Dashboard</h2>
        <p className="text-muted-foreground mt-1">Live overview from current company ledger</p>
      </div>

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Total Assets"
          value={`Rs.${totalAssets.toLocaleString("en-IN")}`}
          subtitle="Cash, Bank, Receivables"
          icon={Wallet}
        />
        <StatCard
          title="Total Liabilities"
          value={`Rs.${totalLiabilities.toLocaleString("en-IN")}`}
          subtitle="Accounts & Tax Payables"
          icon={AlertCircle}
        />
        <StatCard
          title="Net Profit / (Loss)"
          value={`Rs.${netIncome.toLocaleString("en-IN")}`}
          subtitle={`Revenue: Rs.${totalRevenue.toLocaleString("en-IN")}`}
          variant={netIncome >= 0 ? "success" : "warning"}
          icon={netIncome >= 0 ? TrendingUp : TrendingDown}
        />
        <StatCard
          title="Total Equity"
          value={`Rs.${totalEquity.toLocaleString("en-IN")}`}
          subtitle="Capital + Retained Earnings"
          icon={BookOpen}
        />
      </div>

      {/* Charts & Activity */}
      <div className="grid gap-6 md:grid-cols-2">
        {/* Income vs Expense */}
        <Card>
          <CardHeader>
            <CardTitle>Income vs Expense</CardTitle>
            <CardDescription>Current year cumulative revenue and expenses</CardDescription>
          </CardHeader>
          <CardContent>
            {hasFinancialData ? (
              <ResponsiveContainer width="100%" height={260}>
                <BarChart data={performanceData}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} />
                  <XAxis dataKey="category" />
                  <YAxis tickFormatter={(val) => `Rs.${(val / 1000).toFixed(0)}k`} />
                  <Tooltip formatter={(value: any) => `Rs.${Number(value).toLocaleString("en-IN")}`} />
                  <Legend />
                  <Bar dataKey="Revenue" fill="#16a34a" radius={[6, 6, 0, 0]} />
                  <Bar dataKey="Expenses" fill="#dc2626" radius={[6, 6, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-64 flex flex-col items-center justify-center text-muted-foreground text-sm space-y-2">
                <Clock className="w-8 h-8 opacity-40" />
                <p>No transactions recorded yet for this company.</p>
                <p className="text-xs">Post your first voucher to view live revenue charts.</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Account Distribution */}
        <Card>
          <CardHeader>
            <CardTitle>Balance Sheet Distribution</CardTitle>
            <CardDescription>Proportion of Assets, Liabilities, and Equity</CardDescription>
          </CardHeader>
          <CardContent>
            {accountDistribution.length > 0 ? (
              <ResponsiveContainer width="100%" height={260}>
                <PieChart>
                  <Pie
                    data={accountDistribution}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                    outerRadius={80}
                    dataKey="value"
                  >
                    {accountDistribution.map((_, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value: any) => `Rs.${Number(value).toLocaleString("en-IN")}`} />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-64 flex flex-col items-center justify-center text-muted-foreground text-sm space-y-2">
                <BookOpen className="w-8 h-8 opacity-40" />
                <p>No balances to distribute.</p>
                <p className="text-xs">Assets, liabilities, and equity are all at zero.</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Recent Vouchers */}
        <Card className="md:col-span-2">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>Recent Journal Vouchers</CardTitle>
                <CardDescription>Latest double-entry postings in this company</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {recentVouchers.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-muted-foreground">
                      <th className="text-left py-2 px-3">Date</th>
                      <th className="text-left py-2 px-3">Reference / Voucher</th>
                      <th className="text-left py-2 px-3">Description</th>
                      <th className="text-right py-2 px-3">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {recentVouchers.map((v: any, idx: number) => (
                      <tr key={idx} className="border-b hover:bg-muted/50 transition-colors">
                        <td className="py-2.5 px-3">
                          {v.transDate ? v.transDate.split("T")[0] : v.date || today}
                        </td>
                        <td className="py-2.5 px-3 font-semibold text-primary">
                          {v.extra ? JSON.parse(v.extra).reference || v.reference : v.reference || "VOUCHER"}
                        </td>
                        <td className="py-2.5 px-3 text-muted-foreground">
                          {v.description || "Journal entry"}
                        </td>
                        <td className="py-2.5 px-3 text-right">
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/10 text-green-700 dark:text-green-400">
                            Posted ✓
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="py-12 text-center text-muted-foreground text-sm">
                No vouchers posted in this company yet.
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
