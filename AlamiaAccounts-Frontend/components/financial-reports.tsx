"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Download, Eye, Loader2 } from "lucide-react"
import ReportView from "@/components/report-view"
import { useTrialBalance, useProfitLoss, useBalanceSheet } from "@/hooks/use-reports"

export default function FinancialReports({ initialReport }: { initialReport?: string } = {}) {
  const today = new Date().toISOString().split("T")[0]
  const currentYear = new Date().getFullYear()
  const startOfYear = `${currentYear}-01-01`

  const [selectedReport, setSelectedReport] = useState(initialReport || "balance-sheet")
  const [reportPeriod, setReportPeriod] = useState(today) // Used as 'asOfDate' or 'toDate'
  const [fromDate, setFromDate] = useState(startOfYear)
  const [plLayout, setPlLayout] = useState<"income-expense" | "vertical">("income-expense")
  const [viewingReport, setViewingReport] = useState(false)

  const reports = [
    {
      id: "balance-sheet",
      name: "Balance Sheet",
      description: "Statement of financial position",
    },
    {
      id: "profit-loss",
      name: "Profit & Loss Statement",
      description: "Income statement for the period",
    },
    {
      id: "cash-flow",
      name: "Cash Flow Statement",
      description: "Statement of cash flows",
    },
    {
      id: "trial-balance",
      name: "Trial Balance",
      description: "List of all accounts with balances",
    },
  ]

  const { data: apiTrialBalance, isLoading: isLoadingTB } = useTrialBalance(reportPeriod, "PKR")
  const { data: apiPL, isLoading: isLoadingPL } = useProfitLoss(fromDate, reportPeriod, "PKR")
  const { data: apiBS, isLoading: isLoadingBS } = useBalanceSheet(reportPeriod, "PKR")

  // Map backend trial balance to frontend format (handle both array and { accounts: [...] } object)
  const trialBalanceAccounts = Array.isArray(apiTrialBalance)
    ? apiTrialBalance
    : (Array.isArray(apiTrialBalance?.accounts) ? apiTrialBalance.accounts : [])

  const trialBalanceData = trialBalanceAccounts.map((item: any) => ({
    account: item.account_name || item.account || item.code || "Account",
    code: item.account_code || item.code || "",
    debit: Number(item.debit) || 0,
    credit: Number(item.credit) || 0
  }))

  const rawEquity = (Array.isArray(apiBS?.equity) ? apiBS.equity : []).map((item: any) => ({
    account: item.account_name || item.account || "Equity",
    amount: Number(item.amount) || 0
  }))

  if (apiBS?.retained_earnings) {
    rawEquity.push({
      account: "Retained Earnings (Net Profit)",
      amount: Number(apiBS.retained_earnings) || 0
    })
  }

  const balanceSheetData = {
    assets: (Array.isArray(apiBS?.assets) ? apiBS.assets : []).map((item: any) => ({
      account: item.account_name || item.account || "Asset",
      amount: Number(item.amount) || 0
    })),
    liabilities: (Array.isArray(apiBS?.liabilities) ? apiBS.liabilities : []).map((item: any) => ({
      account: item.account_name || item.account || "Liability",
      amount: Number(item.amount) || 0
    })),
    equity: rawEquity,
  }

  const profitLossData = {
    revenue: (Array.isArray(apiPL?.income) ? apiPL.income : []).map((item: any) => ({
      account: item.account_name || item.account || "Income",
      amount: Number(item.amount) || 0
    })),
    expenses: (Array.isArray(apiPL?.expenses) ? apiPL.expenses : []).map((item: any) => ({
      account: item.account_name || item.account || "Expense",
      amount: Number(item.amount) || 0
    })),
  }

  const cashFlowData = {
    operating: [
      { activity: "Net Profit", amount: apiPL?.net_profit || 0 },
      { activity: "Add: Depreciation", amount: 0 },
      { activity: "Less: Increase in Accounts Receivable", amount: 0 },
      { activity: "Add: Increase in Accounts Payable", amount: 0 },
    ],
    investing: [],
    financing: [],
  }

  const renderBalanceSheet = () => {
    const totalAssets = balanceSheetData.assets.reduce((sum, item) => sum + item.amount, 0)
    const totalLiabilities = balanceSheetData.liabilities.reduce((sum, item) => sum + item.amount, 0)
    const totalEquity = balanceSheetData.equity.reduce((sum, item) => sum + item.amount, 0)

    return (
      <div className="space-y-6">
        <div className="grid md:grid-cols-2 gap-6">
          {/* Assets */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="text-lg">Assets</CardTitle>
                {isLoadingBS && <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />}
              </div>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                {balanceSheetData.assets.map((item, i) => (
                  <div key={i} className="flex justify-between text-sm py-2">
                    <span>{item.account}</span>
                    <span className="font-medium">Rs.{item.amount.toLocaleString()}</span>
                  </div>
                ))}
                <div className="border-t pt-2 mt-2 flex justify-between font-bold">
                  <span>Total Assets</span>
                  <span className="text-green-600">Rs.{totalAssets.toLocaleString()}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Liabilities & Equity */}
          <div className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Liabilities</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {balanceSheetData.liabilities.map((item, i) => (
                    <div key={i} className="flex justify-between text-sm py-2">
                      <span>{item.account}</span>
                      <span className="font-medium">Rs.{item.amount.toLocaleString()}</span>
                    </div>
                  ))}
                  <div className="border-t pt-2 mt-2 flex justify-between font-bold">
                    <span>Total Liabilities</span>
                    <span className="text-orange-600">Rs.{totalLiabilities.toLocaleString()}</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Equity</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {balanceSheetData.equity.map((item, i) => (
                    <div key={i} className="flex justify-between text-sm py-2">
                      <span>{item.account}</span>
                      <span className="font-medium">Rs.{item.amount.toLocaleString()}</span>
                    </div>
                  ))}
                  <div className="border-t pt-2 mt-2 flex justify-between font-bold">
                    <span>Total Equity</span>
                    <span className="text-blue-600">Rs.{totalEquity.toLocaleString()}</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        <Card className="bg-muted/50">
          <CardContent className="pt-6">
            <div className="flex justify-between text-lg font-bold">
              <span>Total Liabilities + Equity</span>
              <span className="text-purple-600">Rs.{(totalLiabilities + totalEquity).toLocaleString()}</span>
            </div>
            <p className="text-xs text-muted-foreground mt-2">
              {totalAssets === totalLiabilities + totalEquity
                ? "Balance Sheet is balanced ✓"
                : "Balance Sheet is not balanced"}
            </p>
          </CardContent>
        </Card>
      </div>
    )
  }

  const renderProfitLoss = () => {
    const totalRevenue = profitLossData.revenue.reduce((sum, item) => sum + item.amount, 0)
    const totalExpenses = profitLossData.expenses.reduce((sum, item) => sum + item.amount, 0)
    const netProfit = totalRevenue - totalExpenses

    return (
      <div className="space-y-6">
        {/* Layout Toggle */}
        <div className="flex justify-end">
          <div className="inline-flex rounded-lg border p-1">
            <button
              onClick={() => setPlLayout("income-expense")}
              className={`px-4 py-2 text-sm rounded-md transition-colors ${plLayout === "income-expense" ? "bg-primary text-primary-foreground" : "hover:bg-muted"
                }`}
            >
              Income-Expense Layout
            </button>
            <button
              onClick={() => setPlLayout("vertical")}
              className={`px-4 py-2 text-sm rounded-md transition-colors ${plLayout === "vertical" ? "bg-primary text-primary-foreground" : "hover:bg-muted"
                }`}
            >
              Vertical Layout
            </button>
          </div>
        </div>

        {plLayout === "income-expense" ? (
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Income & Expense Statement</CardTitle>
                {isLoadingPL && <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />}
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Revenue */}
              <div>
                <h3 className="font-semibold mb-3">Revenue</h3>
                <div className="space-y-2 mb-3">
                  {profitLossData.revenue.map((item, i) => (
                    <div key={i} className="flex justify-between text-sm">
                      <span>{item.account}</span>
                      <span>Rs.{item.amount.toLocaleString()}</span>
                    </div>
                  ))}
                </div>
                <div className="border-t pt-2 flex justify-between font-bold">
                  <span>Total Revenue</span>
                  <span className="text-green-600">Rs.{totalRevenue.toLocaleString()}</span>
                </div>
              </div>

              {/* Expenses */}
              <div>
                <h3 className="font-semibold mb-3">Expenses</h3>
                <div className="space-y-2 mb-3">
                  {profitLossData.expenses.map((item, i) => (
                    <div key={i} className="flex justify-between text-sm">
                      <span>{item.account}</span>
                      <span>Rs.{item.amount.toLocaleString()}</span>
                    </div>
                  ))}
                </div>
                <div className="border-t pt-2 flex justify-between font-bold">
                  <span>Total Expenses</span>
                  <span className="text-red-600">Rs.{totalExpenses.toLocaleString()}</span>
                </div>
              </div>

              {/* Net Profit */}
              <div className="bg-muted/50 p-4 rounded-lg">
                <div className="flex justify-between text-lg font-bold">
                  <span>Net Profit / (Loss)</span>
                  <span className={netProfit >= 0 ? "text-green-600" : "text-red-600"}>
                    Rs.{netProfit.toLocaleString()}
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>
        ) : (
          <Card>
            <CardHeader>
              <CardTitle>Profit & Loss Account (Vertical Format)</CardTitle>
            </CardHeader>
            <CardContent>
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2">Particulars</th>
                    <th className="text-right py-2">Amount (Rs.)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-b">
                    <td className="py-2 font-semibold" colSpan={2}>
                      Revenue
                    </td>
                  </tr>
                  {profitLossData.revenue.map((item, i) => (
                    <tr key={i}>
                      <td className="py-2 pl-4">{item.account}</td>
                      <td className="text-right">{item.amount.toLocaleString()}</td>
                    </tr>
                  ))}
                  <tr className="border-t font-bold">
                    <td className="py-2">Total Revenue</td>
                    <td className="text-right text-green-600">{totalRevenue.toLocaleString()}</td>
                  </tr>
                  <tr className="border-b">
                    <td className="py-3 font-semibold" colSpan={2}>
                      Less: Expenses
                    </td>
                  </tr>
                  {profitLossData.expenses.map((item, i) => (
                    <tr key={i}>
                      <td className="py-2 pl-4">{item.account}</td>
                      <td className="text-right">{item.amount.toLocaleString()}</td>
                    </tr>
                  ))}
                  <tr className="border-t font-bold">
                    <td className="py-2">Total Expenses</td>
                    <td className="text-right text-red-600">({totalExpenses.toLocaleString()})</td>
                  </tr>
                  <tr className="border-t-2 border-black">
                    <td className="py-3 font-bold text-lg">Net Profit / (Loss)</td>
                    <td
                      className={`text-right font-bold text-lg ${netProfit >= 0 ? "text-green-600" : "text-red-600"}`}
                    >
                      {netProfit.toLocaleString()}
                    </td>
                  </tr>
                </tbody>
              </table>
            </CardContent>
          </Card>
        )}
      </div>
    )
  }

  const renderCashFlow = () => {
    const operatingTotal = cashFlowData.operating.reduce((sum, item) => sum + item.amount, 0)
    const investingTotal = cashFlowData.investing.reduce((sum, item) => sum + item.amount, 0)
    const financingTotal = cashFlowData.financing.reduce((sum, item) => sum + item.amount, 0)
    const netCashFlow = operatingTotal + investingTotal + financingTotal

    return (
      <Card>
        <CardHeader>
          <CardTitle>Cash Flow Statement</CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Operating Activities */}
          <div>
            <h3 className="font-semibold mb-3">Cash Flow from Operating Activities</h3>
            <div className="space-y-2 mb-3">
              {cashFlowData.operating.map((item, i) => (
                <div key={i} className="flex justify-between text-sm">
                  <span>{item.activity}</span>
                  <span className={item.amount < 0 ? "text-red-600" : ""}>Rs.{item.amount.toLocaleString()}</span>
                </div>
              ))}
            </div>
            <div className="border-t pt-2 flex justify-between font-bold">
              <span>Net Cash from Operating Activities</span>
              <span className={operatingTotal >= 0 ? "text-green-600" : "text-red-600"}>
                Rs.{operatingTotal.toLocaleString()}
              </span>
            </div>
          </div>

          {/* Investing Activities */}
          <div>
            <h3 className="font-semibold mb-3">Cash Flow from Investing Activities</h3>
            <div className="space-y-2 mb-3">
              {cashFlowData.investing.map((item, i) => (
                <div key={i} className="flex justify-between text-sm">
                  <span>{item.activity}</span>
                  <span className={item.amount < 0 ? "text-red-600" : ""}>Rs.{item.amount.toLocaleString()}</span>
                </div>
              ))}
            </div>
            <div className="border-t pt-2 flex justify-between font-bold">
              <span>Net Cash from Investing Activities</span>
              <span className={investingTotal >= 0 ? "text-green-600" : "text-red-600"}>
                Rs.{investingTotal.toLocaleString()}
              </span>
            </div>
          </div>

          {/* Financing Activities */}
          <div>
            <h3 className="font-semibold mb-3">Cash Flow from Financing Activities</h3>
            <div className="space-y-2 mb-3">
              {cashFlowData.financing.map((item, i) => (
                <div key={i} className="flex justify-between text-sm">
                  <span>{item.activity}</span>
                  <span className={item.amount < 0 ? "text-red-600" : ""}>Rs.{item.amount.toLocaleString()}</span>
                </div>
              ))}
            </div>
            <div className="border-t pt-2 flex justify-between font-bold">
              <span>Net Cash from Financing Activities</span>
              <span className={financingTotal >= 0 ? "text-green-600" : "text-red-600"}>
                Rs.{financingTotal.toLocaleString()}
              </span>
            </div>
          </div>

          {/* Net Change */}
          <div className="bg-muted/50 p-4 rounded-lg">
            <div className="flex justify-between text-lg font-bold">
              <span>Net Increase / (Decrease) in Cash</span>
              <span className={netCashFlow >= 0 ? "text-green-600" : "text-red-600"}>
                Rs.{netCashFlow.toLocaleString()}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>
    )
  }

  const renderTrialBalance = () => {
    const totalDebit = trialBalanceData.reduce((sum, item) => sum + item.debit, 0)
    const totalCredit = trialBalanceData.reduce((sum, item) => sum + item.credit, 0)

    return (
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Trial Balance</CardTitle>
            {isLoadingTB && <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />}
          </div>
        </CardHeader>
        <CardContent>
          <table className="w-full">
            <thead>
              <tr className="border-b-2">
                <th className="text-left py-3">Account Code</th>
                <th className="text-left py-3">Account Name</th>
                <th className="text-right py-3">Debit (Rs.)</th>
                <th className="text-right py-3">Credit (Rs.)</th>
              </tr>
            </thead>
            <tbody>
              {trialBalanceData.map((item, i) => (
                <tr key={i} className="border-b">
                  <td className="py-2">{item.code}</td>
                  <td className="py-2">{item.account}</td>
                  <td className="text-right">{item.debit > 0 ? item.debit.toLocaleString() : "—"}</td>
                  <td className="text-right">{item.credit > 0 ? item.credit.toLocaleString() : "—"}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="border-t-2 border-black font-bold">
                <td colSpan={2} className="py-3">
                  Total
                </td>
                <td className="text-right text-green-600">{totalDebit.toLocaleString()}</td>
                <td className="text-right text-blue-600">{totalCredit.toLocaleString()}</td>
              </tr>
            </tfoot>
          </table>
          <div className="mt-4 p-4 bg-muted/50 rounded-lg">
            <p className="text-sm">
              {totalDebit === totalCredit ? (
                <span className="text-green-600 font-semibold">✓ Trial Balance matches (Debit = Credit)</span>
              ) : (
                <span className="text-red-600 font-semibold">⚠ Trial Balance does not match</span>
              )}
            </p>
          </div>
        </CardContent>
      </Card>
    )
  }

  const renderReportContent = () => {
    switch (selectedReport) {
      case "balance-sheet":
        return renderBalanceSheetPrintable()
      case "profit-loss":
        return renderProfitLossPrintable()
      case "cash-flow":
        return renderCashFlowPrintable()
      case "trial-balance":
        return renderTrialBalancePrintable()
      default:
        return null
    }
  }

  const renderBalanceSheetPrintable = () => {
    const totalAssets = balanceSheetData.assets.reduce((sum, item) => sum + item.amount, 0)
    const totalLiabilities = balanceSheetData.liabilities.reduce((sum, item) => sum + item.amount, 0)
    const totalEquity = balanceSheetData.equity.reduce((sum, item) => sum + item.amount, 0)

    return (
      <table className="w-full">
        <thead>
          <tr className="border-b-2 border-black">
            <th className="text-left py-2">Particulars</th>
            <th className="text-right py-2">Amount (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td className="py-2 font-bold" colSpan={2}>
              ASSETS
            </td>
          </tr>
          {balanceSheetData.assets.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.account}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Total Assets</td>
            <td className="text-right">{totalAssets.toLocaleString()}</td>
          </tr>
          <tr>
            <td className="py-3 font-bold" colSpan={2}>
              LIABILITIES
            </td>
          </tr>
          {balanceSheetData.liabilities.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.account}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Total Liabilities</td>
            <td className="text-right">{totalLiabilities.toLocaleString()}</td>
          </tr>
          <tr>
            <td className="py-3 font-bold" colSpan={2}>
              EQUITY
            </td>
          </tr>
          {balanceSheetData.equity.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.account}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Total Equity</td>
            <td className="text-right">{totalEquity.toLocaleString()}</td>
          </tr>
          <tr className="border-t-2 border-black">
            <td className="py-3 font-bold text-lg">Total Liabilities & Equity</td>
            <td className="text-right font-bold text-lg">{(totalLiabilities + totalEquity).toLocaleString()}</td>
          </tr>
        </tbody>
      </table>
    )
  }

  const renderProfitLossPrintable = () => {
    const totalRevenue = profitLossData.revenue.reduce((sum, item) => sum + item.amount, 0)
    const totalExpenses = profitLossData.expenses.reduce((sum, item) => sum + item.amount, 0)
    const netProfit = totalRevenue - totalExpenses

    return (
      <table className="w-full">
        <thead>
          <tr className="border-b-2 border-black">
            <th className="text-left py-2">Particulars</th>
            <th className="text-right py-2">Amount (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td className="py-2 font-bold" colSpan={2}>
              REVENUE
            </td>
          </tr>
          {profitLossData.revenue.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.account}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Total Revenue</td>
            <td className="text-right">{totalRevenue.toLocaleString()}</td>
          </tr>
          <tr>
            <td className="py-3 font-bold" colSpan={2}>
              EXPENSES
            </td>
          </tr>
          {profitLossData.expenses.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.account}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Total Expenses</td>
            <td className="text-right">({totalExpenses.toLocaleString()})</td>
          </tr>
          <tr className="border-t-2 border-black">
            <td className="py-3 font-bold text-lg">Net Profit / (Loss)</td>
            <td className="text-right font-bold text-lg">{netProfit.toLocaleString()}</td>
          </tr>
        </tbody>
      </table>
    )
  }

  const renderCashFlowPrintable = () => {
    const operatingTotal = cashFlowData.operating.reduce((sum, item) => sum + item.amount, 0)
    const investingTotal = cashFlowData.investing.reduce((sum, item) => sum + item.amount, 0)
    const financingTotal = cashFlowData.financing.reduce((sum, item) => sum + item.amount, 0)
    const netCashFlow = operatingTotal + investingTotal + financingTotal

    return (
      <table className="w-full">
        <thead>
          <tr className="border-b-2 border-black">
            <th className="text-left py-2">Particulars</th>
            <th className="text-right py-2">Amount (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td className="py-2 font-bold" colSpan={2}>
              OPERATING ACTIVITIES
            </td>
          </tr>
          {cashFlowData.operating.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.activity}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Net Cash from Operating</td>
            <td className="text-right">{operatingTotal.toLocaleString()}</td>
          </tr>
          <tr>
            <td className="py-3 font-bold" colSpan={2}>
              INVESTING ACTIVITIES
            </td>
          </tr>
          {cashFlowData.investing.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.activity}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Net Cash from Investing</td>
            <td className="text-right">{investingTotal.toLocaleString()}</td>
          </tr>
          <tr>
            <td className="py-3 font-bold" colSpan={2}>
              FINANCING ACTIVITIES
            </td>
          </tr>
          {cashFlowData.financing.map((item, i) => (
            <tr key={i}>
              <td className="py-1 pl-4">{item.activity}</td>
              <td className="text-right">{item.amount.toLocaleString()}</td>
            </tr>
          ))}
          <tr className="border-t font-bold">
            <td className="py-2">Net Cash from Financing</td>
            <td className="text-right">{financingTotal.toLocaleString()}</td>
          </tr>
          <tr className="border-t-2 border-black">
            <td className="py-3 font-bold text-lg">Net Increase / (Decrease) in Cash</td>
            <td className="text-right font-bold text-lg">{netCashFlow.toLocaleString()}</td>
          </tr>
        </tbody>
      </table>
    )
  }

  const renderTrialBalancePrintable = () => {
    const totalDebit = trialBalanceData.reduce((sum, item) => sum + item.debit, 0)
    const totalCredit = trialBalanceData.reduce((sum, item) => sum + item.credit, 0)

    return (
      <table className="w-full">
        <thead>
          <tr className="border-b-2 border-black">
            <th className="text-left py-2">Code</th>
            <th className="text-left py-2">Account Name</th>
            <th className="text-right py-2">Debit (Rs.)</th>
            <th className="text-right py-2">Credit (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          {trialBalanceData.map((item, i) => (
            <tr key={i} className="border-b">
              <td className="py-1">{item.code}</td>
              <td className="py-1">{item.account}</td>
              <td className="text-right">{item.debit > 0 ? item.debit.toLocaleString() : "—"}</td>
              <td className="text-right">{item.credit > 0 ? item.credit.toLocaleString() : "—"}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr className="border-t-2 border-black font-bold">
            <td colSpan={2} className="py-2">
              Total
            </td>
            <td className="text-right">{totalDebit.toLocaleString()}</td>
            <td className="text-right">{totalCredit.toLocaleString()}</td>
          </tr>
        </tfoot>
      </table>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-3xl font-bold tracking-tight">Financial Reports</h2>
        <p className="text-muted-foreground mt-1">Generate and view comprehensive financial statements</p>
      </div>

      {/* Report Selector */}
      <Card>
        <CardContent className="pt-6">
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {reports.map((report) => (
              <button
                key={report.id}
                onClick={() => setSelectedReport(report.id)}
                className={`p-4 rounded-lg border-2 transition-all text-left ${selectedReport === report.id ? "border-primary bg-primary/5" : "border-border hover:border-primary/50"
                  }`}
              >
                <p className="font-semibold text-sm">{report.name}</p>
                <p className="text-xs text-muted-foreground mt-1">{report.description}</p>
              </button>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Date Filter & Actions */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex flex-col md:flex-row gap-4 items-end">
            <div className="flex-1">
              <label className="block text-sm font-medium mb-2">
                {selectedReport === "profit-loss" || selectedReport === "cash-flow" ? "From Date" : "As of Date"}
              </label>
              <input
                type="date"
                value={selectedReport === "profit-loss" || selectedReport === "cash-flow" ? fromDate : reportPeriod}
                onChange={(e) => {
                  if (selectedReport === "profit-loss" || selectedReport === "cash-flow") {
                    setFromDate(e.target.value)
                  } else {
                    setReportPeriod(e.target.value)
                  }
                }}
                className="w-full px-3 py-2 border rounded-lg bg-background"
              />
            </div>
            {(selectedReport === "profit-loss" || selectedReport === "cash-flow") && (
              <div className="flex-1">
                <label className="block text-sm font-medium mb-2">To Date</label>
                <input
                  type="date"
                  value={reportPeriod}
                  onChange={(e) => setReportPeriod(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg bg-background"
                />
              </div>
            )}
            <div className="flex gap-2 w-full md:w-auto">
              <Button className="flex-1 md:flex-initial" onClick={() => setViewingReport(true)}>
                <Eye className="w-4 h-4 mr-2" />
                View Report
              </Button>
              <Button variant="outline" className="flex-1 md:flex-initial bg-transparent">
                <Download className="w-4 h-4 mr-2" />
                Export PDF
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Report Display */}
      {selectedReport === "balance-sheet" && renderBalanceSheet()}
      {selectedReport === "profit-loss" && renderProfitLoss()}
      {selectedReport === "cash-flow" && renderCashFlow()}
      {selectedReport === "trial-balance" && renderTrialBalance()}

      {viewingReport && (
        <ReportView
          title={reports.find((r) => r.id === selectedReport)?.name || "Financial Report"}
          companyName="Acme Corporation"
          reportDate={new Date(reportPeriod).toLocaleDateString("en-IN", {
            year: "numeric",
            month: "long",
            day: "numeric",
          })}
          onClose={() => setViewingReport(false)}
        >
          {renderReportContent()}
        </ReportView>
      )}
    </div>
  )
}
