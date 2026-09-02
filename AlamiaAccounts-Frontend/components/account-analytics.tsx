"use client"

import {
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart"
import { TrendingUp, TrendingDown, DollarSign, Boxes } from "lucide-react"

export default function AccountAnalytics({ groups, accounts }) {
  const groupBalances = groups.map((group) => {
    const groupAccounts = accounts.filter((a) => a.groupId === group.id)
    const total = groupAccounts.reduce((sum, a) => sum + a.balance, 0)
    return {
      name: group.code,
      fullName: group.name,
      balance: total,
      count: groupAccounts.length,
    }
  })

  const assetGroups = ["FA", "CUA"]
  const liabilityGroups = ["CL", "BOR"]
  const equityGroups = ["CA"]

  const totalAssets = groupBalances.filter((g) => assetGroups.includes(g.name)).reduce((sum, g) => sum + g.balance, 0)

  const totalLiabilities = groupBalances
    .filter((g) => liabilityGroups.includes(g.name))
    .reduce((sum, g) => sum + g.balance, 0)

  const totalEquity = groupBalances.filter((g) => equityGroups.includes(g.name)).reduce((sum, g) => sum + g.balance, 0)

  const totalIncome = groupBalances.filter((g) => g.name === "INC").reduce((sum, g) => sum + g.balance, 0)

  const totalExpense = groupBalances
    .filter((g) => ["EXP", "IEXP"].includes(g.name))
    .reduce((sum, g) => sum + g.balance, 0)

  const accountTypeDistribution = Object.entries(
    accounts.reduce((acc, account) => {
      acc[account.type] = (acc[account.type] || 0) + 1
      return acc
    }, {}),
  ).map(([type, count]) => ({
    name: type,
    value: count,
  }))

  const currencyDistribution = Object.entries(
    accounts.reduce((acc, account) => {
      acc[account.currency] = (acc[account.currency] || 0) + account.balance
      return acc
    }, {}),
  ).map(([currency, balance]) => ({
    name: currency,
    balance: balance,
  }))

  const COLORS = ["#3b82f6", "#8b5cf6", "#ec4899", "#f59e0b", "#10b981", "#06b6d4", "#6366f1", "#14b8a6"]

  const KpiCard = ({ icon: Icon, label, value, trend, trendValue, color }) => (
    <div className="p-4 bg-muted rounded-lg">
      <div className="flex items-start justify-between">
        <div className="flex-1">
          <div className="text-xs text-muted-foreground font-medium mb-1">{label}</div>
          <div className="text-2xl font-bold text-foreground">{value}</div>
          {trendValue !== undefined && (
            <div
              className={`text-xs mt-1 flex items-center gap-1 ${trend === "up" ? "text-green-600" : "text-red-600"}`}
            >
              {trend === "up" ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />}
              {trendValue}
            </div>
          )}
        </div>
        <Icon className={`w-5 h-5 ${color}`} />
      </div>
    </div>
  )

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <KpiCard icon={Boxes} label="Total Accounts" value={accounts.length} color="text-blue-500" />
        <KpiCard
          icon={DollarSign}
          label="Total Assets"
          value={`Rs.${(totalAssets / 1000).toFixed(1)}K`}
          color="text-green-500"
        />
        <KpiCard
          icon={DollarSign}
          label="Total Liabilities"
          value={`Rs.${(totalLiabilities / 1000).toFixed(1)}K`}
          color="text-red-500"
        />
        <KpiCard
          icon={DollarSign}
          label="Net Worth"
          value={`Rs.${((totalAssets - totalLiabilities) / 1000).toFixed(1)}K`}
          color="text-purple-500"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <CardHeader>
            <CardTitle>Account Group Balances</CardTitle>
            <CardDescription>Total balance by group</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartContainer
              config={{
                balance: {
                  label: "Balance",
                  color: "hsl(var(--chart-1))",
                },
              }}
              className="h-[300px]"
            >
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={groupBalances}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis />
                  <ChartTooltip content={<ChartTooltipContent />} />
                  <Bar dataKey="balance" fill="var(--color-balance)" radius={[8, 8, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </ChartContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Account Type Distribution</CardTitle>
            <CardDescription>Count by type</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartContainer
              config={{
                count: {
                  label: "Count",
                  color: "hsl(var(--chart-1))",
                },
              }}
              className="h-[300px]"
            >
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={accountTypeDistribution}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, value }) => `${name}: ${value}`}
                    outerRadius={100}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {accountTypeDistribution.map((_, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <ChartTooltip content={<ChartTooltipContent />} />
                </PieChart>
              </ResponsiveContainer>
            </ChartContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Currency Distribution</CardTitle>
            <CardDescription>Balance by currency</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartContainer
              config={{
                balance: {
                  label: "Balance",
                  color: "hsl(var(--chart-2))",
                },
              }}
              className="h-[300px]"
            >
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={currencyDistribution} layout="vertical">
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis type="number" />
                  <YAxis type="category" dataKey="name" />
                  <ChartTooltip content={<ChartTooltipContent />} />
                  <Bar dataKey="balance" fill="var(--color-balance)" radius={[0, 8, 8, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </ChartContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Financial Position</CardTitle>
            <CardDescription>Assets, Liabilities & Equity</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartContainer
              config={{
                assets: { label: "Assets", color: "hsl(var(--chart-3))" },
                liabilities: { label: "Liabilities", color: "hsl(var(--chart-4))" },
                equity: { label: "Equity", color: "hsl(var(--chart-5))" },
              }}
              className="h-[300px]"
            >
              <ResponsiveContainer width="100%" height="100%">
                <BarChart
                  data={[{ name: "Position", assets: totalAssets, liabilities: totalLiabilities, equity: totalEquity }]}
                >
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis />
                  <Tooltip />
                  <Legend />
                  <Bar dataKey="assets" fill="hsl(var(--chart-3))" radius={[8, 8, 0, 0]} />
                  <Bar dataKey="liabilities" fill="hsl(var(--chart-4))" radius={[8, 8, 0, 0]} />
                  <Bar dataKey="equity" fill="hsl(var(--chart-5))" radius={[8, 8, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </ChartContainer>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Accounting Equation</CardTitle>
          <CardDescription>Assets = Liabilities + Equity (Balance Check)</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-3 gap-4">
            <div className="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">
              <div className="text-sm text-muted-foreground font-medium">Total Assets</div>
              <div className="text-2xl font-bold mt-2">Rs.{(totalAssets / 100000).toFixed(2)}L</div>
            </div>
            <div className="flex items-center justify-center">
              <div className="text-2xl font-bold text-muted-foreground">=</div>
            </div>
            <div className="space-y-2">
              <div className="p-2 bg-red-50 dark:bg-red-950 rounded border border-red-200 dark:border-red-800">
                <div className="text-xs text-muted-foreground">Liabilities</div>
                <div className="text-lg font-bold">Rs.{(totalLiabilities / 100000).toFixed(2)}L</div>
              </div>
              <div className="text-sm text-center font-medium text-muted-foreground">+</div>
              <div className="p-2 bg-purple-50 dark:bg-purple-950 rounded border border-purple-200 dark:border-purple-800">
                <div className="text-xs text-muted-foreground">Equity</div>
                <div className="text-lg font-bold">Rs.{(totalEquity / 100000).toFixed(2)}L</div>
              </div>
            </div>
          </div>
          <div className="mt-4 p-3 bg-muted rounded-lg">
            <div className="text-sm text-muted-foreground">
              Balance Status:{" "}
              <span
                className={
                  totalAssets === totalLiabilities + totalEquity
                    ? "text-green-600 font-semibold"
                    : "text-orange-600 font-semibold"
                }
              >
                {totalAssets === totalLiabilities + totalEquity ? "✓ Balanced" : "⚠ Not Balanced"}
              </span>
            </div>
            {totalAssets !== totalLiabilities + totalEquity && (
              <div className="text-xs text-orange-600 mt-1">
                Difference: Rs.{Math.abs(totalAssets - (totalLiabilities + totalEquity)).toLocaleString("en-IN")}
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
