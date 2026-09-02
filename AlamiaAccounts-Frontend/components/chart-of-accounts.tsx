"use client"

import { useState, useMemo } from "react"
import { Plus, Edit, Trash2, Search, Loader2 } from "lucide-react"
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
import { useAccounts } from "@/hooks/use-accounts"

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
      currency: acc.currency || "PKR"
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
        acc.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        acc.code.toLowerCase().includes(searchTerm.toLowerCase()),
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

  const handleDeleteGroup = (groupId: string) => {
    const hasAccounts = accounts.some((acc: any) => acc.groupId === groupId)
    if (hasAccounts) {
      alert("Cannot delete group with existing accounts. Delete accounts first.")
      return
    }
    setGroups(groups.filter((g) => g.id !== groupId))
  }

  const handleAddAccount = (accountData: any) => {
    createAccount.mutate(accountData)
    setShowAccountDialog(false)
  }

  const handleEditAccount = (accountData: any) => {
    updateAccount.mutate({ code: (editingAccount as any).code, data: accountData })
    setEditingAccount(null)
    setShowAccountDialog(false)
  }

  const handleDeleteAccount = (accountId: string) => {
    // accountId is code in our mapping
    deleteAccount.mutate(accountId)
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
                              <div className="font-medium text-sm">{account.balance.toLocaleString("en-IN")}</div>
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
            onSubmit={editingAccount ? handleEditAccount : handleAddAccount}
            onCancel={() => setShowAccountDialog(false)}
          />
        </DialogContent>
      </Dialog>
    </div>
  )
}
