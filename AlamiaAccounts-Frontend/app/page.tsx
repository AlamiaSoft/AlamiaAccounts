"use client"

import { useState, useEffect, useMemo } from "react"
import Sidebar from "@/components/sidebar"
import Dashboard from "@/components/dashboard"
import ChartOfAccounts from "@/components/chart-of-accounts"
import VoucherEntry from "@/components/voucher-entry"
import UserManagement from "@/components/user-management"
import LedgerView from "@/components/ledger-view"
import FinancialReports from "@/components/financial-reports"
import CompanyManagement from "@/components/company-management"
import GlobalSearch from "@/components/global-search"
import type { Company } from "@/components/company-switcher"
import { getVoucherById, getAccountById, getUserById } from "@/lib/sample-data"
import type { Voucher, Account, User } from "@/lib/sample-data"
import VoucherView from "@/components/voucher-view"
import AccountView from "@/components/account-view"
import UserView from "@/components/user-view"
import LedgerDetailView from "@/components/ledger-detail-view"
import PrintTemplateSettings, { type PrintSettings } from "@/components/print-template-settings"
import Cashbook from "@/components/cashbook"
import DayBook from "@/components/daybook"
import CustomVoucherTypes from "@/components/custom-voucher-types"
import VoucherBuilder from "@/components/voucher-builder"
import { useCompanies } from "@/hooks/use-companies"
import { Loader2 } from "lucide-react"

export default function Home() {
  const [currentPage, setCurrentPage] = useState<string>("dashboard")

  // Load active page from URL query param on client mount
  useEffect(() => {
    if (typeof window !== "undefined") {
      const urlParams = new URLSearchParams(window.location.search)
      const pageParam = urlParams.get("page")
      if (pageParam) {
        setCurrentPage(pageParam)
        localStorage.setItem("current_page", pageParam)
      } else {
        setCurrentPage("dashboard")
        localStorage.setItem("current_page", "dashboard")
      }
    }
  }, [])

  const handlePageChange = (page: string) => {
    setCurrentPage(page)
    if (typeof window !== "undefined") {
      localStorage.setItem("current_page", page)
      const url = new URL(window.location.href)
      if (page === "dashboard") {
        url.searchParams.delete("page")
      } else {
        url.searchParams.set("page", page)
      }
      window.history.replaceState({}, "", url.toString())
    }
  }

  const [userRole] = useState<"admin" | "accountant" | "viewer">("admin")
  const [selectedVoucher, setSelectedVoucher] = useState<Voucher | null>(null)
  const [voucherViewMode, setVoucherViewMode] = useState<"view" | "create">("create")
  const [selectedAccount, setSelectedAccount] = useState<Account | null>(null)
  const [selectedUser, setSelectedUser] = useState<User | null>(null)
  const [selectedLedgerAccount, setSelectedLedgerAccount] = useState<{ name: string; code: string } | null>(null)

  // Integration: Use useCompanies hook
  const {
    companies: apiCompanies,
    currentCompany: apiCurrentCompany,
    switchCompany,
    createCompany,
    updateCompany,
    deleteCompany,
    isLoading: isLoadingCompanies
  } = useCompanies()

  // Map API data to component expected format (using code or id)
  const companies = useMemo(
    () => (apiCompanies || []).map((c: any, index: number) => ({
      ...c,
      id: c.code || c.id || `company-${index}`,
      code: c.code || c.id || `company-${index}`,
      name: c.name || c.code || c.id || "Main Company",
      industry: c.industry || "General",
      currency: c.currency || "PKR",
    })),
    [apiCompanies]
  )

  const currentCompany = useMemo(() => {
    if (!apiCurrentCompany) {
      if (companies.length > 0) return companies[0]
      return {
        id: "MAIN",
        code: "MAIN",
        name: "Main Company",
        industry: "General",
        currency: "PKR",
      }
    }
    return {
      ...apiCurrentCompany,
      id: apiCurrentCompany.code || apiCurrentCompany.id || "MAIN",
      code: apiCurrentCompany.code || apiCurrentCompany.id || "MAIN",
      name: apiCurrentCompany.name || apiCurrentCompany.code || "Main Company",
      industry: apiCurrentCompany.industry || "General",
      currency: apiCurrentCompany.currency || "PKR",
    }
  }, [apiCurrentCompany, companies])

  const [printSettings, setPrintSettings] = useState<PrintSettings>({
    companyName: currentCompany?.name || "Acme Corporation",
    companyAddress: "123 Business Park, Islamabad, Punjab 400001",
    companyPhone: "+92 22 1234 5678",
    companyEmail: "accounts@acme.com",
    footerNote: "This is a computer generated document and does not require signature.",
    showHeader: true,
    showFooter: true,
  })

  // Update print settings when company changes
  useEffect(() => {
    if (currentCompany) {
      setPrintSettings(prev => ({
        ...prev,
        companyName: currentCompany.name
      }))
    }
  }, [currentCompany])

  const getSearchContext = (): "vouchers" | "accounts" | "ledgers" | "users" | "reports" | "dashboard" | undefined => {
    switch (currentPage) {
      case "voucher-payment":
      case "voucher-receipt":
      case "voucher-journal":
      case "voucher-contra":
      case "voucher-sales":
      case "voucher-purchase":
      case "cashbook":
      case "daybook":
        return "vouchers"
      case "coa":
        return "accounts"
      case "ledger":
        return "ledgers"
      case "users":
        return "users"
      case "balance-sheet":
      case "profit-loss":
      case "cash-flow":
      case "trial-balance":
        return "reports"
      case "dashboard":
        return "dashboard"
      default:
        return undefined
    }
  }

  const handleCompanyChange = (company: Company) => {
    const code = company.code || company.id
    if (typeof window !== 'undefined') {
      localStorage.setItem('current_company_code', code)
    }
    switchCompany.mutate(code)
    setSelectedVoucher(null)
    setSelectedAccount(null)
    setSelectedUser(null)
    setSelectedLedgerAccount(null)
  }

  const handleAddCompany = () => {
    setCurrentPage("companies")
  }

  const handleSearchResultClick = (result: { id: string; type: string; title: string }) => {
    console.log("[v0] Search result clicked:", JSON.stringify(result))

    switch (result.type) {
      case "voucher":
        const voucher = getVoucherById(result.id)
        if (voucher) {
          setSelectedVoucher(voucher)
          setVoucherViewMode("view")
          setCurrentPage("voucher-view")
        }
        break
      case "account":
        const account = getAccountById(result.id)
        if (account) {
          setSelectedAccount(account)
          setCurrentPage("account-view")
        }
        break
      case "ledger":
        const ledgerAccount = getAccountById(result.id)
        if (ledgerAccount) {
          setSelectedLedgerAccount({ name: ledgerAccount.name, code: ledgerAccount.code })
          setCurrentPage("ledger-detail-view")
        }
        break
      case "user":
      case "role":
        const user = getUserById(result.id)
        if (user) {
          setSelectedUser(user)
          setCurrentPage("user-view")
        }
        break
      case "company":
        setCurrentPage("companies")
        break
    }
  }

  const handleAddCompanySubmit = (company: Omit<Company, "id">) => {
    // API expects code, name, industry. Ensure code is present.
    // The form might not provide code if it was designed for mock data with auto-id.
    // We might need to generate a code or ask user for it.
    // For now, let's assume the form provides it or we generate it from name.
    const companyData = {
      ...company,
      code: (company as any).code || company.name.toUpperCase().replace(/\s+/g, '').substring(0, 10)
    }
    createCompany.mutate(companyData)
  }

  const handleEditCompany = (id: string, company: Omit<Company, "id">) => {
    updateCompany.mutate({ code: id, data: company })
  }

  const handleDeleteCompany = (id: string) => {
    deleteCompany.mutate(id, {
      onSuccess: () => {
        if (currentCompany?.code === id || currentCompany?.id === id) {
          const fallback = companies.find((c) => c.id !== id)?.code || "MAIN"
          switchCompany.mutate(fallback)
        }
      }
    })
  }

  if (isLoadingCompanies && !currentCompany) {
    return (
      <div className="flex items-center justify-center h-screen">
        <Loader2 className="w-8 h-8 animate-spin" />
      </div>
    )
  }

  // If no companies exist (and not loading), we might want to show a setup screen
  // But for now we'll let it render, Sidebar might handle empty state or we rely on default data

  const renderPage = () => {
    switch (currentPage) {
      case "dashboard":
        return <Dashboard />
      case "coa":
        return <ChartOfAccounts />
      case "users":
        return <UserManagement />
      case "companies":
        return (
          <CompanyManagement
            companies={companies}
            onAddCompany={handleAddCompanySubmit}
            onEditCompany={handleEditCompany}
            onDeleteCompany={handleDeleteCompany}
          />
        )
      case "custom-voucher-types":
        return <CustomVoucherTypes />
      case "voucher-builder":
        return <VoucherBuilder />
      case "voucher-view":
        if (selectedVoucher) {
          return (
            <VoucherView
              voucher={selectedVoucher}
              printSettings={printSettings}
              onBack={() => {
                setCurrentPage("dashboard")
                setSelectedVoucher(null)
              }}
              onEdit={() => {
                const voucherTypePage = `voucher-${selectedVoucher.type}` as any
                setCurrentPage(voucherTypePage)
              }}
            />
          )
        }
        return <Dashboard />
      case "account-view":
        if (selectedAccount) {
          const groupName = "Current Assets"
          return (
            <AccountView
              account={selectedAccount}
              groupName={groupName}
              onBack={() => {
                setCurrentPage("coa")
                setSelectedAccount(null)
              }}
              onEdit={() => {
                setCurrentPage("coa")
              }}
            />
          )
        }
        return <ChartOfAccounts />
      case "user-view":
        if (selectedUser) {
          return (
            <UserView
              user={selectedUser}
              onBack={() => {
                setCurrentPage("users")
                setSelectedUser(null)
              }}
              onEdit={() => {
                setCurrentPage("users")
              }}
            />
          )
        }
        return <UserManagement />
      case "ledger-detail-view":
        if (selectedLedgerAccount) {
          return (
            <LedgerDetailView
              accountName={selectedLedgerAccount.name}
              accountCode={selectedLedgerAccount.code}
              onBack={() => {
                setCurrentPage("ledger")
                setSelectedLedgerAccount(null)
              }}
            />
          )
        }
        return <LedgerView />
      case "voucher-payment":
      case "voucher-receipt":
      case "voucher-journal":
      case "voucher-contra":
      case "voucher-sales":
      case "voucher-purchase":
        const voucherType = currentPage.replace("voucher-", "")
        return (
          <VoucherEntry
            selectedVoucher={selectedVoucher}
            onClearSelection={() => {
              setSelectedVoucher(null)
              setVoucherViewMode("create")
            }}
            defaultVoucherType={voucherType}
          />
        )
      case "cashbook":
        return <Cashbook />
      case "daybook":
        return <DayBook />
      case "ledger":
        return <LedgerView />
      case "trial-balance":
      case "balance-sheet":
      case "profit-loss":
      case "cash-flow":
        return <FinancialReports initialReport={currentPage} />
      case "print-templates":
        return <PrintTemplateSettings onSave={setPrintSettings} initialSettings={printSettings} />
      default:
        return <Dashboard />
    }
  }

  return (
    <div className="flex h-screen bg-background">
      <Sidebar
        currentPage={currentPage}
        onPageChange={handlePageChange}
        userRole={userRole}
        companies={companies}
        currentCompany={currentCompany || companies[0]}
        onCompanyChange={handleCompanyChange}
        onAddCompany={handleAddCompany}
      />
      <main className="flex-1 overflow-y-auto">
        <div className="sticky top-0 z-10 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 border-b border-border">
          <div className="max-w-7xl mx-auto px-6 lg:px-8 py-4">
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2 text-sm text-muted-foreground flex-shrink-0">
                <span className="font-medium text-foreground">Active:</span>
                {currentCompany?.name || companies[0]?.name || "Main Company"}
              </div>
              <div className="flex-1 max-w-xl">
                <GlobalSearch
                  currentCompany={currentCompany || companies[0]}
                  currentContext={getSearchContext()}
                  onResultClick={handleSearchResultClick}
                />
              </div>
            </div>
          </div>
        </div>
        <div className="max-w-7xl mx-auto p-6 lg:p-8">{renderPage()}</div>
      </main>
    </div>
  )
}
