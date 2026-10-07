"use client"

import { useState, useEffect, useMemo } from "react"
import {
  BookOpen,
  Search,
  Printer,
  ExternalLink,
  Sparkles,
  ArrowRight,
  CheckCircle2,
  FileText,
  Shield,
  Layers,
  Terminal,
  ChevronRight,
  Plane,
  Wallet,
  Lock,
  BarChart3,
  HelpCircle,
  Hash,
} from "lucide-react"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs"
import { apiClient } from "@/lib/api-client"

interface ManualChapter {
  id: string
  title: string
  module: string
  tags: string[]
  api_endpoints?: string[]
  invariants?: string[]
  body: string
  filename: string
}

interface SopWorkflow {
  topic: string
  domain: string
  title: string
  trigger_keywords: string[]
  actions: Array<{ label: string; action: string; payload?: any }>
  body: string
}

interface CustomPolicy {
  id: number
  topic: string
  title: string
  summary: string
  steps: string[]
  note: string | null
  actions: any[]
  company_code: string | null
}

export default function UserManual() {
  const [chapters, setChapters] = useState<ManualChapter[]>([])
  const [workflows, setWorkflows] = useState<SopWorkflow[]>([])
  const [customPolicies, setCustomPolicies] = useState<CustomPolicy[]>([])
  const [activeSection, setActiveSection] = useState<string>("overview")
  const [searchQuery, setSearchQuery] = useState<string>("")
  const [isLoading, setIsLoading] = useState<boolean>(true)

  useEffect(() => {
    async function fetchManualData() {
      setIsLoading(true)
      try {
        const res = await apiClient.get("/manual")
        if (res.data?.success) {
          setChapters(res.data.data.chapters || [])
          setWorkflows(res.data.data.workflows || [])
          setCustomPolicies(res.data.data.custom_policies || [])
        }
      } catch (err) {
        console.error("Failed to load manual data:", err)
      } finally {
        setIsLoading(false)
      }
    }
    fetchManualData()
  }, [])

  const handleAskCopilot = (prompt: string) => {
    window.dispatchEvent(
      new CustomEvent("copilot:open", {
        detail: { prompt, context: { source: "user_manual" } },
      })
    )
  }

  const handleNavigateApp = (page: string) => {
    if (typeof window !== "undefined") {
      const url = new URL(window.location.href)
      url.searchParams.set("page", page)
      window.location.href = url.toString()
    }
  }

  // Pre-configured structured topics for the accounting playbook
  const playbookTopics = [
    {
      id: "overview",
      category: "1. System Overview",
      title: "Software Overview & Double-Entry Architecture",
      icon: BookOpen,
      desc: "System structure, multi-tenancy, and Abivia Ledger double-entry engine.",
      prompts: ["What accounting standard does Alamia Accounts follow?", "How does multi-company isolation work?"],
    },
    {
      id: "opening_balances",
      category: "2. Getting Started",
      title: "Compound Opening Balances Setup (OB-)",
      icon: Layers,
      desc: "Establishing balanced starting positions for Assets, Liabilities, and Capital equity.",
      prompts: ["How to post opening balances?", "What account holds retained earnings difference?"],
      action: { label: "⚖️ Open Opening Balances", page: "opening-balances" },
    },
    {
      id: "vouchers_and_daybook",
      category: "3. Daily Transactions",
      title: "Daily Voucher Entry & Daybook Posting",
      icon: Wallet,
      desc: "Entering Payment, Receipt, Journal, Contra, and Sales vouchers without unbalance.",
      prompts: ["How to create a payment voucher?", "Show ledger activity for Cash (1110)"],
      action: { label: "📝 New Voucher Entry", page: "voucher-payment" },
    },
    {
      id: "voucher_correction",
      category: "4. Error Correction",
      title: "Voucher Reversal & Correction Workflow (REV-)",
      icon: Shield,
      desc: "GAAP/IFRS compliance: Why vouchers are immutable and how compensating reversals work.",
      prompts: ["How do I fix a wrong voucher amount?", "How to reverse a posted transaction?"],
      action: { label: "📄 Open Day Book", page: "daybook" },
    },
    {
      id: "chart_of_accounts",
      category: "5. Chart of Accounts",
      title: "4-Digit Chart of Accounts & Subledgers",
      icon: Hash,
      desc: "Category folders (1120 Bank) vs. leaf posting accounts (1130 Meezan Bank).",
      prompts: ["How to add a new bank account?", "What is the 4-digit code for Sales Revenue?"],
      action: { label: "📖 Open Chart of Accounts", page: "coa" },
    },
    {
      id: "period_locking",
      category: "6. Month-End Closing",
      title: "Fiscal Accounting Periods & Period Locking",
      icon: Lock,
      desc: "Locking monthly periods to prevent retroactive tampering and viewing the audit trail.",
      prompts: ["How to close an accounting period?", "Why was a voucher posting blocked in closed month?"],
      action: { label: "🔒 Accounting Periods", page: "periods" },
    },
    {
      id: "financial_reports",
      category: "7. Financial Statements",
      title: "Real-Time Reports: Trial Balance, P&L, Balance Sheet",
      icon: BarChart3,
      desc: "Generating Dr === Cr Trial Balance, Income Statement Net Profit, and Solvency Sheets.",
      prompts: ["Show Trial Balance summary", "Show Profit & Loss report for this year", "Show Balance Sheet summary"],
      action: { label: "📊 Generate Trial Balance", page: "trial-balance" },
    },
    {
      id: "sales_pos_api",
      category: "8. Integrations & POS",
      title: "Front-Office Sales & POS API Integration (Travel / Retail)",
      icon: Plane,
      desc: "Connecting Kamal Express booking desks and external POS to auto-post SV- & RV- vouchers.",
      prompts: ["How does the POS Sales API connect to Alamia Accounts?", "Where can I find the Sales API guide?"],
    },
  ]

  const filteredTopics = useMemo(() => {
    if (!searchQuery.trim()) return playbookTopics
    const q = searchQuery.toLowerCase()
    return playbookTopics.filter(
      (t) =>
        t.title.toLowerCase().includes(q) ||
        t.desc.toLowerCase().includes(q) ||
        t.category.toLowerCase().includes(q)
    )
  }, [searchQuery, playbookTopics])

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-16 text-foreground">
      {/* Header Banner */}
      <div className="bg-card border border-border/80 p-6 md:p-8 rounded-2xl shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="space-y-2">
          <div className="flex items-center gap-2.5">
            <div className="p-2.5 bg-sky-600 text-white rounded-xl shadow-sm">
              <BookOpen className="w-6 h-6" />
            </div>
            <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
              Accountant Help & User Manual Portal
            </h1>
          </div>
          <p className="text-sm text-muted-foreground max-w-2xl leading-relaxed">
            Comprehensive operational playbooks, SOPs, and integration guides for accountants, cashiers, and front-office staff using Alamia Accounts.
          </p>
        </div>

        <div className="flex items-center gap-3 shrink-0">
          <Button
            variant="outline"
            size="sm"
            onClick={() => window.print()}
            className="gap-1.5 font-semibold border-border text-foreground hover:bg-accent"
          >
            <Printer className="w-4 h-4" /> Print Manual
          </Button>
          <Button
            variant="default"
            size="sm"
            onClick={() => window.open("/?page=manual", "_blank")}
            className="gap-1.5 bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow-sm"
          >
            <ExternalLink className="w-4 h-4" /> Open in New Tab
          </Button>
        </div>
      </div>

      {/* Search & Quick Filter Bar */}
      <div className="relative">
        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        <Input
          placeholder="Search accounting workflows, SOPs, voucher types, API endpoints..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="pl-10 h-11 bg-card border-border text-foreground placeholder:text-muted-foreground text-sm shadow-sm"
        />
      </div>

      {/* Main Content Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* Left Navigation Playbook Index (4 cols) */}
        <div className="lg:col-span-4 space-y-2">
          <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground px-1 pb-1">
            Accounting Playbook Index
          </div>
          <div className="space-y-2">
            {filteredTopics.map((topic) => {
              const Icon = topic.icon
              const isActive = activeSection === topic.id
              return (
                <button
                  key={topic.id}
                  onClick={() => setActiveSection(topic.id)}
                  className={`w-full text-left p-3.5 rounded-xl border transition-all flex items-start gap-3.5 ${
                    isActive
                      ? "bg-sky-50 dark:bg-sky-950/60 border-sky-500 text-foreground ring-1 ring-sky-500 shadow-sm"
                      : "bg-card border-border hover:bg-accent/50 text-foreground hover:border-border/80"
                  }`}
                >
                  <div
                    className={`p-2 rounded-lg mt-0.5 shrink-0 ${
                      isActive
                        ? "bg-sky-600 text-white shadow-sm"
                        : "bg-muted text-foreground"
                    }`}
                  >
                    <Icon className="w-4 h-4" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div
                      className={`text-[11px] font-bold uppercase tracking-wider ${
                        isActive ? "text-sky-700 dark:text-sky-300" : "text-sky-600 dark:text-sky-400"
                      }`}
                    >
                      {topic.category}
                    </div>
                    <div className="font-bold text-sm text-foreground truncate mt-0.5">{topic.title}</div>
                    <p className="text-xs text-muted-foreground line-clamp-2 mt-1 leading-relaxed">{topic.desc}</p>
                  </div>
                </button>
              )
            })}
          </div>
        </div>

        {/* Right Reading & Action Pane (8 cols) */}
        <div className="lg:col-span-8 space-y-6">
          {activeSection === "overview" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <div className="flex items-center justify-between">
                  <Badge className="bg-sky-100 text-sky-800 border border-sky-300 dark:bg-sky-950 dark:text-sky-200 dark:border-sky-800 font-semibold">
                    Core System Architecture
                  </Badge>
                  <span className="text-xs font-mono font-bold text-muted-foreground">GAAP / IFRS Compliant</span>
                </div>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">Alamia Accounts Software Architecture</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  Institutional double-entry ledger with multi-tenant domain isolation.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-5 text-sm leading-relaxed text-foreground">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="p-4 bg-muted/40 rounded-xl border border-border space-y-2">
                    <div className="font-bold text-foreground flex items-center gap-1.5 text-xs uppercase tracking-wider">
                      <Shield className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Double-Entry Rule
                    </div>
                    <p className="text-xs text-muted-foreground leading-relaxed">
                      Every transaction strictly enforces <code className="font-mono font-semibold px-1.5 py-0.5 rounded bg-muted border border-border text-foreground">sum(Debits) === sum(Credits)</code>. Ledger unbalance is mathematically blocked.
                    </p>
                  </div>
                  <div className="p-4 bg-muted/40 rounded-xl border border-border space-y-2">
                    <div className="font-bold text-foreground flex items-center gap-1.5 text-xs uppercase tracking-wider">
                      <Layers className="w-4 h-4 text-indigo-600 dark:text-indigo-400" /> Tenant Isolation
                    </div>
                    <p className="text-xs text-muted-foreground leading-relaxed">
                      Transactions are strictly partitioned per company domain. Transactions in Main Company never leak into Kamal Express.
                    </p>
                  </div>
                </div>

                <div className="space-y-3">
                  <h3 className="font-bold text-foreground text-sm uppercase tracking-wider">Key Modules for Daily Work</h3>
                  <ul className="space-y-2 text-xs text-muted-foreground">
                    <li className="flex items-start gap-2">
                      <CheckCircle2 className="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" />
                      <span><strong className="text-foreground">Books of Prime Entry</strong>: Daybook provides real-time chronological ledger of all vouchers. Cashbook tracks cash inflows and outflows.</span>
                    </li>
                    <li className="flex items-start gap-2">
                      <CheckCircle2 className="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" />
                      <span><strong className="text-foreground">Voucher Types</strong>: Payment (PV), Receipt (RV), Journal (JV), Contra (CV), and Sales (SV).</span>
                    </li>
                    <li className="flex items-start gap-2">
                      <CheckCircle2 className="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" />
                      <span><strong className="text-foreground">Real-Time Reports</strong>: Trial Balance with verified drill-down, Profit & Loss, and Solvency Balance Sheet.</span>
                    </li>
                  </ul>
                </div>

                {/* Copilot Quick Prompts Box */}
                <div className="p-4 bg-sky-50 dark:bg-sky-950/40 border border-sky-300 dark:border-sky-800 rounded-xl space-y-2.5">
                  <div className="font-bold text-xs text-sky-800 dark:text-sky-300 flex items-center gap-1.5 uppercase tracking-wider">
                    <Sparkles className="w-3.5 h-3.5" /> Ask AI Copilot (Click to Try)
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={() => handleAskCopilot("Show Trial Balance summary")}
                      className="text-xs font-semibold bg-background border border-sky-300 dark:border-sky-700 hover:bg-sky-100 dark:hover:bg-sky-900/50 text-foreground"
                    >
                      "Show Trial Balance summary"
                    </Button>
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={() => handleAskCopilot("What is the balance of Meezan Bank?")}
                      className="text-xs font-semibold bg-background border border-sky-300 dark:border-sky-700 hover:bg-sky-100 dark:hover:bg-sky-900/50 text-foreground"
                    >
                      "What is the balance of Meezan Bank?"
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {activeSection === "opening_balances" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <Badge className="bg-amber-100 text-amber-900 border border-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:border-amber-800 font-semibold w-fit">
                  Tenant Onboarding
                </Badge>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">Compound Balanced Opening Position Setup</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  How to establish initial financial positions when onboarding a new business or fiscal year.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-4 text-sm leading-relaxed text-foreground">
                <div className="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 rounded-xl text-xs space-y-1.5">
                  <div className="font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">Double-Entry Invariant Rule</div>
                  <p className="text-muted-foreground">
                    All balance sheet accounts (Assets, Liabilities, Equity) must balance compoundly: <code className="font-mono font-semibold px-1 py-0.5 rounded bg-background border text-foreground">Total Debits === Total Credits</code>. Any net difference must be allocated to <strong className="text-foreground">Capital (3100)</strong> or <strong className="text-foreground">Retained Earnings (3200)</strong>.
                  </p>
                </div>

                <div className="space-y-2">
                  <h4 className="font-bold text-foreground text-xs uppercase tracking-wider">Step-by-Step Procedure:</h4>
                  <ol className="space-y-2 text-xs text-muted-foreground list-decimal list-inside">
                    <li>Navigate to <strong className="text-foreground">Opening Balances</strong> in the sidebar.</li>
                    <li>Enter starting debit and credit amounts for each Balance Sheet account.</li>
                    <li>Verify the live total shows zero unallocated difference.</li>
                    <li>Click <strong className="text-foreground">Post Opening Balance Batch</strong>. The batch commits as <code className="font-mono font-semibold px-1 py-0.5 rounded bg-background border text-foreground">OB-YYYY-001</code>.</li>
                  </ol>
                </div>

                <div className="pt-2 flex items-center justify-between border-t border-border">
                  <Button onClick={() => handleNavigateApp("opening-balances")} className="bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs gap-1.5 shadow-sm">
                    ⚖️ Open Opening Balances Screen <ArrowRight className="w-3.5 h-3.5" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {activeSection === "vouchers_and_daybook" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <Badge className="bg-indigo-100 text-indigo-900 border border-indigo-300 dark:bg-indigo-950 dark:text-indigo-200 dark:border-indigo-800 font-semibold w-fit">
                  Daily Operations
                </Badge>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">Voucher Entry & Daybook Playbook</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  Standard operating procedures for entering and reviewing accounting vouchers.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-4 text-sm leading-relaxed text-foreground">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                  <div className="p-3.5 bg-muted/40 rounded-xl border border-border">
                    <div className="font-bold text-foreground mb-1">Receipt Voucher (RV)</div>
                    <p className="text-muted-foreground">Records funds received. Dr Cash/Bank, Cr Revenue or Accounts Receivable.</p>
                  </div>
                  <div className="p-3.5 bg-muted/40 rounded-xl border border-border">
                    <div className="font-bold text-foreground mb-1">Payment Voucher (PV)</div>
                    <p className="text-muted-foreground">Records funds disbursed. Dr Expense or Vendor Payable, Cr Cash/Bank.</p>
                  </div>
                  <div className="p-3.5 bg-muted/40 rounded-xl border border-border">
                    <div className="font-bold text-foreground mb-1">Contra Voucher (CV)</div>
                    <p className="text-muted-foreground">Internal funds transfer (Cash to Bank, Bank to Cash, Bank to Bank).</p>
                  </div>
                  <div className="p-3.5 bg-muted/40 rounded-xl border border-border">
                    <div className="font-bold text-foreground mb-1">Journal Voucher (JV)</div>
                    <p className="text-muted-foreground">Adjustments, accruals, depreciation, and general balance entries.</p>
                  </div>
                </div>

                <div className="pt-2 flex items-center justify-between border-t border-border gap-2">
                  <Button onClick={() => handleNavigateApp("voucher-payment")} className="bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs gap-1.5 shadow-sm">
                    📝 Create New Voucher <ArrowRight className="w-3.5 h-3.5" />
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => handleNavigateApp("daybook")}
                    className="text-xs font-semibold border-border text-foreground hover:bg-accent gap-1.5"
                  >
                    📄 View Daybook
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {activeSection === "voucher_correction" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <Badge className="bg-rose-100 text-rose-900 border border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800 font-semibold w-fit">
                  Audit & Compliance
                </Badge>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">Voucher Correction & Reversal Workflow (REV-)</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  Institutional standards: Why posted vouchers are immutable and how to correct mistakes.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-4 text-sm leading-relaxed text-foreground">
                <div className="p-4 bg-rose-50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800 rounded-xl text-xs space-y-1.5">
                  <div className="font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider">GAAP/IFRS Historical Immutability</div>
                  <p className="text-muted-foreground">
                    Posted accounting vouchers can <strong className="text-foreground">never</strong> be edited in place or deleted from the database. Deleting entries destroys the permanent audit history.
                  </p>
                </div>

                <div className="space-y-2 text-xs">
                  <h4 className="font-bold text-foreground uppercase tracking-wider">How to Correct an Erroneous Voucher:</h4>
                  <ol className="space-y-2 text-muted-foreground list-decimal list-inside">
                    <li>Navigate to the <strong className="text-foreground">Daybook</strong> and locate the voucher (or search via <kbd className="font-mono font-bold bg-muted px-1.5 py-0.5 rounded border border-border text-foreground">Ctrl+K</kbd>).</li>
                    <li>Click the <strong className="text-foreground">Reverse Voucher</strong> button and document the business reason (e.g., <em>"Incorrect amount entered by cashier"</em>).</li>
                    <li>The system automatically creates a compensating entry prefixed with <code className="font-mono font-semibold px-1 py-0.5 rounded bg-background border text-foreground">REV-</code>, which zeroes out the erroneous figures in the ledger.</li>
                    <li>Post a new replacement voucher with the correct accounts and figures.</li>
                  </ol>
                </div>

                <div className="pt-2 flex items-center justify-between border-t border-border">
                  <Button onClick={() => handleNavigateApp("daybook")} className="bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs gap-1.5 shadow-sm">
                    📄 Open Daybook to Reverse Voucher <ArrowRight className="w-3.5 h-3.5" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {activeSection === "chart_of_accounts" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <Badge className="bg-emerald-100 text-emerald-900 border border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-800 font-semibold w-fit">
                  Master Data
                </Badge>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">4-Digit Chart of Accounts Hierarchy</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  Folder category structure and leaf posting accounts.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-4 text-sm leading-relaxed text-foreground">
                <div className="space-y-2 text-xs">
                  <p className="text-muted-foreground">
                    Alamia Accounts uses standard 4-digit code prefixes:
                  </p>
                  <div className="grid grid-cols-2 md:grid-cols-5 gap-2 font-mono text-xs">
                    <div className="p-2.5 bg-muted/60 rounded-lg border border-border text-center text-foreground font-semibold"><strong className="text-sky-600 dark:text-sky-400">1000</strong> Assets</div>
                    <div className="p-2.5 bg-muted/60 rounded-lg border border-border text-center text-foreground font-semibold"><strong className="text-amber-600 dark:text-amber-400">2000</strong> Liabilities</div>
                    <div className="p-2.5 bg-muted/60 rounded-lg border border-border text-center text-foreground font-semibold"><strong className="text-purple-600 dark:text-purple-400">3000</strong> Equity</div>
                    <div className="p-2.5 bg-muted/60 rounded-lg border border-border text-center text-foreground font-semibold"><strong className="text-emerald-600 dark:text-emerald-400">4000</strong> Expenses</div>
                    <div className="p-2.5 bg-muted/60 rounded-lg border border-border text-center text-foreground font-semibold"><strong className="text-indigo-600 dark:text-indigo-400">5000</strong> Revenue</div>
                  </div>
                </div>

                <div className="p-4 bg-muted/40 rounded-xl border border-border space-y-2 text-xs">
                  <div className="font-bold text-foreground uppercase tracking-wider">Category Accounts vs. Leaf Accounts</div>
                  <p className="text-muted-foreground leading-relaxed">
                    • <strong className="text-foreground">Category Folders</strong> (e.g. <code className="font-mono font-semibold px-1 py-0.5 rounded bg-background border text-foreground">1120 Bank Accounts</code>) group related sub-accounts together. Transactions <em>cannot</em> be posted to folders.<br />
                    • <strong className="text-foreground">Leaf Accounts</strong> (e.g. <code className="font-mono font-semibold px-1 py-0.5 rounded bg-background border text-foreground">1130 Meezan Bank</code>, <code className="font-mono font-semibold px-1 py-0.5 rounded bg-background border text-foreground">1135 Habib Bank</code>) are posting accounts where vouchers land.
                  </p>
                </div>

                <div className="pt-2 flex items-center justify-between border-t border-border">
                  <Button onClick={() => handleNavigateApp("coa")} className="bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs gap-1.5 shadow-sm">
                    📖 Open Chart of Accounts <ArrowRight className="w-3.5 h-3.5" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {activeSection === "period_locking" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <Badge className="bg-purple-100 text-purple-900 border border-purple-300 dark:bg-purple-950 dark:text-purple-200 dark:border-purple-800 font-semibold w-fit">
                  Month-End Controls
                </Badge>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">Fiscal Accounting Periods & Lock Management</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  Protecting historical financial integrity against retroactive tampering.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-4 text-sm leading-relaxed text-foreground">
                <div className="space-y-2 text-xs text-muted-foreground">
                  <p>
                    Each fiscal year is partitioned into 12 monthly accounting periods.
                  </p>
                  <ol className="space-y-2 list-decimal list-inside text-foreground">
                    <li>Once month-end reconciliations are complete, navigate to <strong className="text-foreground">Accounting Periods</strong>.</li>
                    <li>Click <strong className="text-foreground">Close Period</strong> for that month.</li>
                    <li>Any subsequent attempt to post a voucher dated within that closed period will be <strong className="text-rose-600 dark:text-rose-400">blocked (HTTP 422)</strong>.</li>
                    <li>Reopening a period requires documented manager justification and is recorded in the permanent audit trail.</li>
                  </ol>
                </div>

                <div className="pt-2 flex items-center justify-between border-t border-border">
                  <Button onClick={() => handleNavigateApp("periods")} className="bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs gap-1.5 shadow-sm">
                    🔒 Manage Accounting Periods <ArrowRight className="w-3.5 h-3.5" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {activeSection === "financial_reports" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <Badge className="bg-sky-100 text-sky-900 border border-sky-300 dark:bg-sky-950 dark:text-sky-200 dark:border-sky-800 font-semibold w-fit">
                  Reporting Engine
                </Badge>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">Real-Time Financial Reports</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  Trial Balance, Profit & Loss Statement, and Solvency Balance Sheet.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-4 text-sm leading-relaxed text-foreground">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                  <div className="p-3.5 bg-muted/40 rounded-xl border border-border">
                    <div className="font-bold text-foreground mb-1">Trial Balance</div>
                    <p className="text-muted-foreground">Mathematical verification that total debits strictly equal total credits.</p>
                  </div>
                  <div className="p-3.5 bg-muted/40 rounded-xl border border-border">
                    <div className="font-bold text-foreground mb-1">Profit & Loss (P&L)</div>
                    <p className="text-muted-foreground">Net operating income calculation (Revenue minus Cost of Sales and Expenses).</p>
                  </div>
                  <div className="p-3.5 bg-muted/40 rounded-xl border border-border">
                    <div className="font-bold text-foreground mb-1">Balance Sheet</div>
                    <p className="text-muted-foreground">Assets = Liabilities + Equity + Retained Earnings snapshot.</p>
                  </div>
                </div>

                <div className="pt-2 flex items-center justify-between border-t border-border gap-2">
                  <Button onClick={() => handleNavigateApp("trial-balance")} className="bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs gap-1.5 shadow-sm">
                    📊 View Trial Balance <ArrowRight className="w-3.5 h-3.5" />
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => handleNavigateApp("profit-loss")}
                    className="text-xs font-semibold border-border text-foreground hover:bg-accent gap-1.5"
                  >
                    📈 Profit & Loss Statement
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {activeSection === "sales_pos_api" && (
            <Card className="border-border bg-card shadow-sm">
              <CardHeader className="border-b border-border bg-muted/40 pb-4">
                <Badge className="bg-emerald-100 text-emerald-900 border border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-800 font-semibold w-fit">
                  External Integration
                </Badge>
                <CardTitle className="text-xl font-bold mt-2 text-foreground">Front-Office Sales & POS API Guide</CardTitle>
                <CardDescription className="text-xs text-muted-foreground">
                  How booking desks (Kamal Express Travels) and retail POS apps connect to Alamia Accounts.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-6 space-y-4 text-sm leading-relaxed text-foreground">
                <p className="text-xs text-muted-foreground leading-relaxed">
                  External frontline systems submit sales via <code className="font-mono font-semibold px-1 py-0.5 rounded bg-muted border border-border text-foreground">POST /api/v1/sales</code>. The gateway automatically resolves customer subledgers and posts balanced <strong className="text-foreground">Sales Vouchers (SV-)</strong> and <strong className="text-foreground">Receipt Vouchers (RV-)</strong>.
                </p>

                <div className="p-4 bg-slate-900 dark:bg-slate-950 text-slate-100 rounded-xl font-mono text-xs overflow-x-auto border border-slate-700 space-y-1.5 shadow-inner">
                  <div className="text-emerald-400 font-bold"># API Endpoint & Headers:</div>
                  <div className="text-slate-200">POST /api/v1/sales</div>
                  <div className="text-slate-300">Authorization: Bearer &lt;API_KEY&gt;</div>
                  <div className="text-slate-300">X-Company-Code: KAMAL_EXPRESS</div>
                </div>

                <div className="space-y-2 text-xs">
                  <h4 className="font-bold text-foreground uppercase tracking-wider">Features for Front-Office Staff:</h4>
                  <ul className="space-y-1.5 text-muted-foreground list-disc list-inside">
                    <li><strong className="text-foreground">Staff Data Scoping</strong>: Counter agents only see their own sales transactions.</li>
                    <li><strong className="text-foreground">Instant Receipts</strong>: Returns printable client receipt HTML and voucher codes immediately.</li>
                    <li><strong className="text-foreground">Idempotency Protection</strong>: Resending duplicate requests returns original receipt without double-billing.</li>
                  </ul>
                </div>

                <div className="pt-2 flex items-center justify-between border-t border-border">
                  <Button
                    onClick={() => window.open("/ke-pos.html", "_blank")}
                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs gap-1.5 shadow-sm"
                  >
                    🚀 Open Kamal Express POS Demo <ExternalLink className="w-3.5 h-3.5" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  )
}
