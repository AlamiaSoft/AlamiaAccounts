"use client"

import { useState } from "react"
import { Home, BarChart3, BookOpen, Wallet, Settings, LogOut, ChevronDown, PieChart } from "lucide-react"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import CompanySwitcher, { type Company } from "./company-switcher"

interface SidebarProps {
  currentPage: string
  onPageChange: (page: string) => void
  userRole?: "admin" | "accountant" | "viewer"
  companies: Company[]
  currentCompany: Company
  onCompanyChange: (company: Company) => void
  onAddCompany: () => void
}

export default function Sidebar({
  currentPage,
  onPageChange,
  userRole = "admin",
  companies,
  currentCompany,
  onCompanyChange,
  onAddCompany,
}: SidebarProps) {
  const [expandedMenu, setExpandedMenu] = useState<string | null>("masters")

  const menuItems = [
    { id: "dashboard", label: "Dashboard", icon: Home },
    {
      id: "masters",
      label: "Masters",
      icon: Settings,
      submenu: [
        { id: "coa", label: "Chart of Accounts" },
        { id: "periods", label: "Accounting Periods" },
        { id: "users", label: "Users & Roles" },
        { id: "companies", label: "Companies" },
        { id: "custom-voucher-types", label: "Custom Voucher Types" },
        { id: "voucher-builder", label: "Voucher Builder", badge: "Experimental" },
        { id: "print-templates", label: "Print Templates" },
      ],
    },
    {
      id: "vouchers",
      label: "Vouchers",
      icon: Wallet,
      submenu: [
        { id: "voucher-payment", label: "Payment Voucher" },
        { id: "voucher-receipt", label: "Receipt Voucher" },
        { id: "voucher-journal", label: "Journal Voucher" },
        { id: "voucher-contra", label: "Contra Voucher" },
        { id: "voucher-sales", label: "Sales Voucher" },
        { id: "voucher-purchase", label: "Purchase Voucher" },
      ],
    },
    {
      id: "transactions",
      label: "Transactions",
      icon: BookOpen,
      submenu: [
        { id: "cashbook", label: "Cashbook" },
        { id: "daybook", label: "Day Book" },
      ],
    },
    {
      id: "accounts",
      label: "Accounts",
      icon: BarChart3,
      submenu: [
        { id: "ledger", label: "General Ledger" },
        { id: "trial-balance", label: "Trial Balance" },
      ],
    },
    {
      id: "reports",
      label: "Reports",
      icon: PieChart,
      submenu: [
        { id: "balance-sheet", label: "Balance Sheet" },
        { id: "profit-loss", label: "Profit & Loss" },
        { id: "cash-flow", label: "Cash Flow" },
      ],
    },
  ]

  return (
    <aside className="w-64 bg-sidebar border-r border-sidebar-border h-screen flex flex-col">
      {/* Header */}
      <div className="p-6 border-b border-sidebar-border">
        <div className="flex items-center gap-2 mb-4">
          <div className="w-10 h-10 rounded-lg bg-sidebar-primary/10 flex items-center justify-center overflow-hidden p-1">
            <img src="/alamia-logo.png" alt="Alamia Logo" className="w-full h-full object-contain" />
          </div>
          <div>
            <h1 className="font-bold text-sidebar-foreground">
              {process.env.NEXT_PUBLIC_APP_NAME || "Alamia Accounts"}
            </h1>
            <p className="text-xs text-muted-foreground">Accounting Suite</p>
          </div>
        </div>

        {/* Company Switcher */}
        <CompanySwitcher
          companies={companies}
          currentCompany={currentCompany}
          onCompanyChange={onCompanyChange}
          onAddCompany={onAddCompany}
        />
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto p-4 space-y-2">
        {menuItems.map((item) => (
          <div key={item.id}>
            <button
              onClick={() => {
                if (item.submenu) {
                  setExpandedMenu(expandedMenu === item.id ? null : item.id)
                } else {
                  onPageChange(item.id)
                }
              }}
              className={cn(
                "w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors",
                currentPage === item.id || expandedMenu === item.id
                  ? "bg-sidebar-accent text-sidebar-accent-foreground"
                  : "text-sidebar-foreground hover:bg-sidebar-border",
              )}
            >
              <item.icon className="w-5 h-5" />
              <span className="flex-1 text-left">{item.label}</span>
              {item.submenu && (
                <ChevronDown className={cn("w-4 h-4 transition-transform", expandedMenu === item.id && "rotate-180")} />
              )}
            </button>

            {/* Submenu */}
            {item.submenu && expandedMenu === item.id && (
              <div className="ml-6 space-y-1 mt-1">
                {item.submenu.map((subitem) => (
                  <button
                    key={subitem.id}
                    onClick={() => onPageChange(subitem.id)}
                    className={cn(
                      "w-full flex items-center justify-between text-left px-4 py-2 rounded-md text-sm transition-colors",
                      currentPage === subitem.id
                        ? "bg-sidebar-accent text-sidebar-accent-foreground font-semibold"
                        : "text-muted-foreground hover:text-sidebar-foreground hover:bg-sidebar-border/30",
                    )}
                  >
                    <span>{subitem.label}</span>
                    {"badge" in subitem && (
                      <span className="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                        {(subitem as any).badge}
                      </span>
                    )}
                  </button>
                ))}
              </div>
            )}
          </div>
        ))}
      </nav>

      {/* Footer */}
      <div className="p-4 border-t border-sidebar-border space-y-2">
        <div className="text-xs text-muted-foreground px-2">
          <p className="font-medium text-sidebar-foreground mb-1">User Role</p>
          <p className="capitalize">{userRole}</p>
        </div>
        <Button
          variant="ghost"
          size="sm"
          className="w-full justify-start text-sidebar-foreground hover:bg-sidebar-border"
        >
          <LogOut className="w-4 h-4 mr-2" />
          Logout
        </Button>
      </div>
    </aside>
  )
}
