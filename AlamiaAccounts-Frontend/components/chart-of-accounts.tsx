"use client"

import { useState, useMemo } from "react"
import { Plus, Edit, Trash2, Search, Loader2, Scale } from "lucide-react"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import AccountGroupForm from "./account-group-form"
import AccountMasterForm from "./account-master-form"
import AccountTreeView from "./account-tree-view"
import AccountAnalytics from "./account-analytics"
import OpeningBalanceModal from "./opening-balance-modal"
import { useAccounts } from "@/hooks/use-accounts"
import ConfirmModal from "@/components/ui/confirm-modal"

// Sample data structure
const DEFAULT_GROUPS = [
  { id: "capital", name: "Capital Account", code: "CA", description: "Owner capital and reserves", order: 1 },
  { id: "fixed", name: "Fixed Assets", code: "FA", description: "Long-term assets", order: 2 },
  { id: "current", name: "Current Assets", code: "CUA", description: "Short-term assets", order: 3 },
  { id: "liability", name: "Current Liabilities", code: "CL", description: "Short-term obligations", order: 4 },
  { id: "income", name: "Income", code: "INC", description: "Revenue accounts", order: 5 },
  { id: "expense", name: "Expenses", code: "EXP", description: "Operating expenses", order: 6 },
]

export default function ChartOfAccounts() {
  const [groups, setGroups] = useState(DEFAULT_GROUPS)
  // Integration: Use useAccounts hook
  const {
    accounts: apiAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
    isLoading
  } = useAccounts()

  // Build hierarchical tree from flat accounts
  const buildAccountTree = (items: any[], parentCode: string | null = null): any[] => {
    return items
      .filter(item => (item.parent_code === parentCode) || (!parentCode && !item.parent_code))
      .map(item => ({
        ...item,
        id: item.code,
        name: item.name || item.names?.[0]?.name || item.code,
        balance: typeof item.balance === 'number' ? item.balance : 0,
        type: item.type || (item.debit ? "Debit" : "Credit"),
        children: buildAccountTree(items, item.code)
      }))
  }

  const accountTree = useMemo(() => {
    return buildAccountTree(apiAccounts || [])
  }, [apiAccounts])

  // Flat accounts for search and list view
  const accounts = useMemo(() => {
    return (apiAccounts || []).map((acc: any) => ({
      ...acc,
      id: acc.code,
      name: acc.name || acc.names?.[0]?.name || acc.code,
      currency: acc.currency || "PKR",
      balance: typeof acc.balance === 'number' ? acc.balance : 0,
      type: acc.type || (acc.debit ? "Debit" : "Credit"),
    }))
  }, [apiAccounts])

  const [searchTerm, setSearchTerm] = useState("")
  const [activeTab, setActiveTab] = useState("tree-view")
  const [showGroupDialog, setShowGroupDialog] = useState(false)
  const [showAccountDialog, setShowAccountDialog] = useState(false)
  const [editingGroup, setEditingGroup] = useState(null)
  const [editingAccount, setEditingAccount] = useState(null)

  const filteredAccounts = useMemo(() => {
    if (!searchTerm) return accounts
    return accounts.filter(
      (acc: any) =>
        (acc.name || "").toLowerCase().includes(searchTerm.toLowerCase()) ||
        (acc.code || "").toLowerCase().includes(searchTerm.toLowerCase()),
    )
  }, [accounts, searchTerm])

  const handleAddGroup = (groupData: any) => {
    const newGroup = {
      ...groupData,
      id: `group-${Date.now()}`,
      order: groups.length + 1,
    }
    setGroups([...groups, newGroup])
    setShowGroupDialog(false)
  }

  const handleEditGroup = (groupData: any) => {
    setGroups(groups.map((g) => (g.id === (editingGroup as any).id ? { ...g, ...groupData } : g)))
    setEditingGroup(null)
    setShowGroupDialog(false)
  }

  // Modal state for confirmations & notices (no browser alerts/confirms)
  const [deleteTarget, setDeleteTarget] = useState<any | null>(null)
  const [openingBalancePrompt, setOpeningBalancePrompt] = useState<{ accountData: any; isEdit: boolean } | null>(null)
  const [noticeModal, setNoticeModal] = useState<{ title: string; message: string; variant?: "danger" | "warning" | "info" } | null>(null)
  const [isOpeningBalanceModalOpen, setIsOpeningBalanceModalOpen] = useState(false)

  const handleDeleteGroup = (groupId: string) => {
    const hasAccounts = accounts.some((acc: any) => acc.groupId === groupId)
    if (hasAccounts) {
      setNoticeModal({
        title: "Cannot Delete Group",
        message: "This account group contains active accounts. Please reassign or delete the associated accounts first.",
        variant: "warning",
      })
      return
    }
    setGroups(groups.filter((g) => g.id !== groupId))
  }

  const [statusAlert, setStatusAlert] = useState<{ type: "success" | "error"; message: string } | null>(null)

  const executeSaveAccount = async (accountData: any, isEdit: boolean) => {
    try {
      if (isEdit) {
        await updateAccount.mutateAsync({ code: (editingAccount as any).code, data: accountData })
        setStatusAlert({ type: "success", message: `Account ${accountData.code} updated successfully!` })
        setEditingAccount(null)
      } else {
        await createAccount.mutateAsync(accountData)
        setStatusAlert({ type: "success", message: `Account ${accountData.code} - ${accountData.name} created successfully!` })
      }
      setShowAccountDialog(false)
      setOpeningBalancePrompt(null)
      setTimeout(() => setStatusAlert(null), 5000)
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message || "Failed to save account"
      setStatusAlert({ type: "error", message: msg })
      setOpeningBalancePrompt(null)
    }
  }

  const handleAddAccount = async (accountData: any) => {
    // If setting an opening balance, confirm impact with user first
    if (accountData.opening_balance && accountData.opening_balance > 0) {
      setShowAccountDialog(false)
      setOpeningBalancePrompt({ accountData, isEdit: false })
      return
    }
    await executeSaveAccount(accountData, false)
  }

  const handleEditAccount = async (accountData: any) => {
    // If setting an opening balance, confirm impact with user first
    const prevBalance = (editingAccount as any)?.balance || 0
    if (accountData.opening_balance && accountData.opening_balance > 0 && accountData.opening_balance !== prevBalance) {
      setShowAccountDialog(false)
      setOpeningBalancePrompt({ accountData, isEdit: true })
      return
    }
    await executeSaveAccount(accountData, true)
  }

  const handleDeleteAccount = (accountIdentifier: any) => {
    const acc = typeof accountIdentifier === "string"
      ? accounts.find((a: any) => a.code === accountIdentifier || a.id === accountIdentifier) || { code: accountIdentifier, name: accountIdentifier }
      : accountIdentifier
    setDeleteTarget(acc)
  }

  const executeDeleteAccount = async () => {
    if (!deleteTarget) return
    const code = deleteTarget.code || deleteTarget.id
    try {
      await deleteAccount.mutateAsync(code)
      setStatusAlert({ type: "success", message: `Account ${code} deleted successfully!` })
      setDeleteTarget(null)
      setTimeout(() => setStatusAlert(null), 5000)
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message || "Failed to delete account"
      setStatusAlert({ type: "error", message: msg })
      setDeleteTarget(null)
    }
  }

  const openGroupDialog = (group = null) => {
    setEditingGroup(group)
    setShowGroupDialog(true)
  }

  const openAccountDialog = (account = null) => {
    setEditingAccount(account)
    setShowAccountDialog(true)
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <Loader2 className="w-8 h-8 animate-spin" />
      </div>
    )
  }
  return (
    <div className="min-h-screen bg-background">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Chart of Accounts</h1>
            <p className="text-muted-foreground mt-1">Manage your account structure and master data</p>
          </div>
          <div className="flex gap-2">
            <Button onClick={() => setIsOpeningBalanceModalOpen(true)} variant="secondary" className="gap-2">
              <Scale className="w-4 h-4" />
              Opening Balances
            </Button>
            <Button onClick={() => openGroupDialog()} className="gap-2">
              <Plus className="w-4 h-4" />
              Add Group
            </Button>
            <Button onClick={() => openAccountDialog()} variant="outline" className="gap-2">
              <Plus className="w-4 h-4" />
              Add Account
            </Button>
          </div>
        </div>

        {statusAlert && (
          <div
            className={`p-4 mb-4 rounded-lg text-sm flex items-center justify-between shadow-sm ${
              statusAlert.type === "success"
                ? "bg-green-500/15 text-green-700 dark:text-green-300 border border-green-500/30"
                : "bg-destructive/15 text-destructive border border-destructive/30"
            }`}
          >
            <span>{statusAlert.message}</span>
            <button
              onClick={() => setStatusAlert(null)}
              className="font-bold ml-4 text-xs hover:opacity-75"
            >
              ✕
            </button>
          </div>
        )}

        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
          <TabsList className="grid w-full max-w-md grid-cols-4">
            <TabsTrigger value="tree-view">Tree View</TabsTrigger>
            <TabsTrigger value="list-view">List View</TabsTrigger>
            <TabsTrigger value="groups">Groups</TabsTrigger>
            <TabsTrigger value="analytics">Analytics</TabsTrigger>
          </TabsList>

          <TabsContent value="tree-view" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Account Hierarchy</CardTitle>
                <CardDescription>View and manage your account structure</CardDescription>
              </CardHeader>
              <CardContent>
                <AccountTreeView
                  accounts={accountTree}
                  onEditAccount={openAccountDialog}
                  onDeleteAccount={handleDeleteAccount}
                />
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="list-view" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Account List</CardTitle>
                <CardDescription>Search and manage individual accounts</CardDescription>
                <div className="flex items-center gap-2 mt-4">
                  <Search className="w-4 h-4 text-muted-foreground" />
                  <Input
                    placeholder="Search by name or code..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="flex-1"
                  />
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {filteredAccounts.length === 0 ? (
                    <p className="text-sm text-muted-foreground text-center py-8">No accounts found</p>
                  ) : (
                    filteredAccounts.map((account) => {
                      const group = groups.find((g) => g.id === account.groupId)
                      return (
                        <div
                          key={account.id}
                          className="flex items-center justify-between p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                        >
                          <div className="flex-1">
                            <div className="font-medium text-sm">
                              {account.code} - {account.name}
                            </div>
                            <div className="text-xs text-muted-foreground">{group?.name}</div>
                          </div>
                          <div className="flex items-center gap-4 mr-4">
                            <div className="text-right">
                              <div className="font-medium text-sm">{(Number(account.balance) || 0).toLocaleString("en-IN")}</div>
                              <Badge variant="outline" className="text-xs">
                                {account.type}
                              </Badge>
                            </div>
                          </div>
                          <div className="flex gap-1">
                            <Button size="sm" variant="ghost" onClick={() => openAccountDialog(account)}>
                              <Edit className="w-4 h-4" />
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => handleDeleteAccount(account.id)}>
                              <Trash2 className="w-4 h-4 text-destructive" />
                            </Button>
                          </div>
                        </div>
                      )
                    })
                  )}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="groups" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Account Groups</CardTitle>
                <CardDescription>Manage account group categories</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {groups
                    .sort((a, b) => a.order - b.order)
                    .map((group) => (
                      <div
                        key={group.id}
                        className="flex items-center justify-between p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                      >
                        <div className="flex-1">
                          <div className="font-medium text-sm">
                            {group.code} - {group.name}
                          </div>
                          <div className="text-xs text-muted-foreground">{group.description}</div>
                        </div>
                        <div className="flex gap-1">
                          <Badge variant="secondary">
                            {accounts.filter((a) => a.groupId === group.id).length} accounts
                          </Badge>
                          <Button size="sm" variant="ghost" onClick={() => openGroupDialog(group)}>
                            <Edit className="w-4 h-4" />
                          </Button>
                          <Button size="sm" variant="ghost" onClick={() => handleDeleteGroup(group.id)}>
                            <Trash2 className="w-4 h-4 text-destructive" />
                          </Button>
                        </div>
                      </div>
                    ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="analytics" className="space-y-4">
            <AccountAnalytics groups={groups} accounts={accounts} />
          </TabsContent>
        </Tabs>
      </div>

      <Dialog open={showGroupDialog} onOpenChange={setShowGroupDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingGroup ? "Edit Account Group" : "Add Account Group"}</DialogTitle>
            <DialogDescription>
              {editingGroup ? "Update group details" : "Create a new account group"}
            </DialogDescription>
          </DialogHeader>
          <AccountGroupForm
            group={editingGroup}
            onSubmit={editingGroup ? handleEditGroup : handleAddGroup}
            onCancel={() => setShowGroupDialog(false)}
          />
        </DialogContent>
      </Dialog>

      <Dialog open={showAccountDialog} onOpenChange={setShowAccountDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingAccount ? "Edit Account" : "Add Account"}</DialogTitle>
            <DialogDescription>
              {editingAccount ? "Update account details" : "Create a new ledger account"}
            </DialogDescription>
          </DialogHeader>
          <AccountMasterForm
            groups={groups}
            account={editingAccount}
            availableAccounts={accounts}
            onSubmit={editingAccount ? handleEditAccount : handleAddAccount}
            onCancel={() => setShowAccountDialog(false)}
          />
        </DialogContent>
      </Dialog>

      {/* 1. Delete Account Confirmation Modal */}
      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={executeDeleteAccount}
        variant="danger"
        title={`Delete Account ${deleteTarget?.code || ""}?`}
        description={`Are you sure you want to delete "${deleteTarget?.name}" (${deleteTarget?.code})? Accounts with recorded transactions cannot be deleted.`}
        confirmText="Delete Account"
        isLoading={deleteAccount.isPending}
      />

      {/* 2. Opening Balance Double-Entry Impact Confirmation Modal */}
      {openingBalancePrompt && (
        <ConfirmModal
          isOpen={true}
          onClose={() => {
            setOpeningBalancePrompt(null)
            setShowAccountDialog(true)
          }}
          onConfirm={() =>
            executeSaveAccount(
              openingBalancePrompt.accountData,
              openingBalancePrompt.isEdit
            )
          }
          variant="warning"
          title="Confirm Opening Balance Entry"
          description={`Setting an opening balance of Rs. ${(
            Number(openingBalancePrompt.accountData.opening_balance) || 0
          ).toLocaleString()} on account ${openingBalancePrompt.accountData.code} (${openingBalancePrompt.accountData.name}) directly affects the accounting equation.`}
          confirmText="Apply Balance & Save"
          isLoading={createAccount.isPending || updateAccount.isPending}
          details={
            <div className="p-3 bg-muted/50 rounded-lg border border-border text-xs space-y-2">
              <div className="font-semibold text-foreground flex items-center justify-between">
                <span>Automatic Double-Entry Offset:</span>
                <Badge variant="outline" className="text-[10px]">
                  Balanced Invariant
                </Badge>
              </div>
              <div className="space-y-1 font-mono text-muted-foreground">
                <div className="flex justify-between">
                  <span>
                    {openingBalancePrompt.accountData.debit ? "Debit (Dr)" : "Credit (Cr)"}: [
                    {openingBalancePrompt.accountData.code}] {openingBalancePrompt.accountData.name}
                  </span>
                  <span className="font-semibold text-foreground">
                    Rs. {(Number(openingBalancePrompt.accountData.opening_balance) || 0).toLocaleString()}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span>
                    {openingBalancePrompt.accountData.debit ? "Credit (Cr)" : "Debit (Dr)"}: [5100] Owner's Capital
                  </span>
                  <span className="font-semibold text-foreground">
                    Rs. {(Number(openingBalancePrompt.accountData.opening_balance) || 0).toLocaleString()}
                  </span>
                </div>
              </div>
              <p className="text-[11px] text-muted-foreground/80 italic pt-1 border-t border-border/50">
                This ensures: Assets = Liabilities + Equity remains in equilibrium.
              </p>
            </div>
          }
        />
      )}

      {/* 3. General Informative Notice Modal */}
      {noticeModal && (
        <ConfirmModal
          isOpen={true}
          onClose={() => setNoticeModal(null)}
          onConfirm={() => setNoticeModal(null)}
          variant={noticeModal.variant || "warning"}
          title={noticeModal.title}
          description={noticeModal.message}
          confirmText="Understood"
          cancelText="Close"
        />
      )}

      {/* 4. Compound Opening Balance Setup Modal */}
      <OpeningBalanceModal
        open={isOpeningBalanceModalOpen}
        onOpenChange={setIsOpeningBalanceModalOpen}
        accounts={apiAccounts || []}
      />
    </div>
  )
}
