"use client"

import {
  BarChart,
  Bar,
  LineChart,
  Line,
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { TrendingUp, TrendingDown, Wallet, BookOpen } from "lucide-react"

const dashboardData = [
  { month: "Jan", income: 40000, expense: 24000 },
  { month: "Feb", income: 45000, expense: 22000 },
  { month: "Mar", income: 50000, expense: 26000 },
  { month: "Apr", income: 52000, expense: 25000 },
  { month: "May", income: 60000, expense: 28000 },
  { month: "Jun", income: 65000, expense: 30000 },
]

const accountDistribution = [
  { name: "Assets", value: 450000 },
  { name: "Liabilities", value: 200000 },
  { name: "Equity", value: 250000 },
]

const COLORS = ["#7c3aed", "#f97316", "#06b6d4"]

const StatCard = ({
  title,
  value,
  trend,
  icon: Icon,
}: {
  title: string
  value: string
  trend: number
  icon: typeof TrendingUp
}) => (
  <Card>
    <CardContent className="pt-6">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-muted-foreground mb-1">{title}</p>
          <p className="text-2xl font-bold">{value}</p>
        </div>
        <div
          className={cn(
            "p-3 rounded-lg",
            trend >= 0 ? "bg-green-100 dark:bg-green-900/30" : "bg-red-100 dark:bg-red-900/30",
          )}
        >
          {trend >= 0 ? (
            <TrendingUp className="w-5 h-5 text-green-600" />
          ) : (
            <TrendingDown className="w-5 h-5 text-red-600" />
          )}
        </div>
      </div>
      <p className={cn("text-xs mt-2", trend >= 0 ? "text-green-600" : "text-red-600")}>
        {trend >= 0 ? "+" : ""}
        {trend}% from last month
      </p>
    </CardContent>
  </Card>
)

import { cn } from "@/lib/utils"

export default function Dashboard() {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-3xl font-bold tracking-tight">Dashboard</h2>
        <p className="text-muted-foreground mt-1">Welcome back! Here's your financial overview.</p>
      </div>

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard title="Total Assets" value="Rs.45,00,000" trend={12} icon={Wallet} />
        <StatCard title="Total Liabilities" value="Rs.20,00,000" trend={-5} icon={Wallet} />
        <StatCard title="Net Income" value="Rs.15,50,000" trend={8} icon={TrendingUp} />
        <StatCard title="Total Equity" value="Rs.25,00,000" trend={3} icon={BookOpen} />
      </div>

      {/* Charts */}
      <div className="grid gap-6 md:grid-cols-2">
        {/* Income vs Expense */}
        <Card>
          <CardHeader>
            <CardTitle>Income vs Expense</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={dashboardData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="income" fill="#7c3aed" radius={[8, 8, 0, 0]} />
                <Bar dataKey="expense" fill="#f97316" radius={[8, 8, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Account Distribution */}
        <Card>
          <CardHeader>
            <CardTitle>Account Distribution</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={accountDistribution}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, value }) => `${name}: Rs.${(value / 100000).toFixed(1)}L`}
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {accountDistribution.map((_, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Cash Flow Trend */}
        <Card className="md:col-span-2">
          <CardHeader>
            <CardTitle>Cash Flow Trend</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={dashboardData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Line type="monotone" dataKey="income" stroke="#7c3aed" strokeWidth={2} dot={{ fill: "#7c3aed" }} />
                <Line type="monotone" dataKey="expense" stroke="#f97316" strokeWidth={2} dot={{ fill: "#f97316" }} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
