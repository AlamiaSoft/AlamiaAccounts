"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Trash2, Plus, Edit2, Loader2 } from "lucide-react"
import { useUsers, type ApiUser } from "@/hooks/use-users"

const ROLES = [
  { value: "admin", label: "Admin", permissions: ["All"] },
  {
    value: "accountant",
    label: "Accountant",
    permissions: ["Transactions", "Reports", "Ledgers"],
  },
  {
    value: "viewer",
    label: "Viewer",
    permissions: ["View Reports", "View Ledgers"],
  },
]

export default function UserManagement() {
  const { users, isLoading, createUser, updateUser, deleteUser } = useUsers()

  const [showForm, setShowForm] = useState(false)
  const [editingUserId, setEditingUserId] = useState<string | number | null>(null)
  const [formData, setFormData] = useState({ name: "", email: "", password: "", role: "accountant" })
  const [errorMsg, setErrorMsg] = useState("")

  const handleOpenCreate = () => {
    setEditingUserId(null)
    setFormData({ name: "", email: "", password: "", role: "accountant" })
    setErrorMsg("")
    setShowForm(true)
  }

  const handleOpenEdit = (user: ApiUser) => {
    setEditingUserId(user.id)
    setFormData({ name: user.name, email: user.email, password: "", role: user.role })
    setErrorMsg("")
    setShowForm(true)
  }

  const handleSubmit = async () => {
    if (!formData.name || !formData.email) {
      setErrorMsg("Name and Email are required.")
      return
    }

    try {
      if (editingUserId) {
        await updateUser.mutateAsync({
          id: editingUserId,
          data: {
            name: formData.name,
            email: formData.email,
            role: formData.role,
            ...(formData.password ? { password: formData.password } : {}),
          },
        })
      } else {
        await createUser.mutateAsync({
          name: formData.name,
          email: formData.email,
          password: formData.password || "password",
          role: formData.role,
          status: "active",
        })
      }
      setShowForm(false)
      setEditingUserId(null)
      setFormData({ name: "", email: "", password: "", role: "accountant" })
      setErrorMsg("")
    } catch (err: any) {
      setErrorMsg(err.response?.data?.message || "Failed to save user.")
    }
  }

  const handleDelete = async (id: string | number) => {
    if (confirm("Are you sure you want to delete this user?")) {
      try {
        await deleteUser.mutateAsync(id)
      } catch (err: any) {
        alert(err.response?.data?.message || "Failed to delete user.")
      }
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Users & Roles</h2>
          <p className="text-muted-foreground mt-1">Manage system user accounts and permissions</p>
        </div>
        <Button onClick={handleOpenCreate}>
          <Plus className="w-4 h-4 mr-2" />
          Add User
        </Button>
      </div>

      {/* Add / Edit User Form */}
      {showForm && (
        <Card>
          <CardHeader>
            <CardTitle>{editingUserId ? "Edit User" : "Add New User"}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {errorMsg && (
              <div className="p-3 text-sm text-destructive bg-destructive/10 rounded-md border border-destructive/20">
                {errorMsg}
              </div>
            )}
            <div>
              <label className="block text-sm font-medium mb-1">Name</label>
              <Input
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                placeholder="Full name"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Email</label>
              <Input
                type="email"
                value={formData.email}
                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                placeholder="Email address"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">
                Password {editingUserId && <span className="text-xs text-muted-foreground">(leave blank to keep current)</span>}
              </label>
              <Input
                type="password"
                value={formData.password}
                onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                placeholder={editingUserId ? "••••••••" : "Minimum 6 characters"}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Role</label>
              <Select value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {ROLES.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                      {role.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex gap-2 pt-4">
              <Button onClick={handleSubmit} disabled={createUser.isPending || updateUser.isPending}>
                {(createUser.isPending || updateUser.isPending) && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
                {editingUserId ? "Update User" : "Save User"}
              </Button>
              <Button variant="outline" onClick={() => setShowForm(false)}>
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Users Table */}
      <Card>
        <CardHeader>
          <CardTitle>User Accounts</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-primary" />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-3 px-4 font-semibold">Name</th>
                    <th className="text-left py-3 px-4 font-semibold">Email</th>
                    <th className="text-left py-3 px-4 font-semibold">Role</th>
                    <th className="text-left py-3 px-4 font-semibold">Status</th>
                    <th className="text-left py-3 px-4 font-semibold">Permissions</th>
                    <th className="text-left py-3 px-4 font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {users.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="text-center py-8 text-muted-foreground">
                        No users found.
                      </td>
                    </tr>
                  ) : (
                    users.map((user) => {
                      const roleConfig = ROLES.find((r) => r.value === user.role) || ROLES[1]
                      return (
                        <tr key={user.id} className="border-b hover:bg-muted/50">
                          <td className="py-3 px-4 font-medium">{user.name}</td>
                          <td className="py-3 px-4 text-muted-foreground">{user.email}</td>
                          <td className="py-3 px-4">
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                              {roleConfig?.label}
                            </span>
                          </td>
                          <td className="py-3 px-4">
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                              {user.status || "active"}
                            </span>
                          </td>
                          <td className="py-3 px-4 text-muted-foreground text-xs">{roleConfig?.permissions.join(", ")}</td>
                          <td className="py-3 px-4">
                            <div className="flex gap-2">
                              <Button variant="ghost" size="sm" onClick={() => handleOpenEdit(user)}>
                                <Edit2 className="w-4 h-4" />
                              </Button>
                              <Button variant="ghost" size="sm" onClick={() => handleDelete(user.id)}>
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Role Permissions Reference */}
      <Card>
        <CardHeader>
          <CardTitle>Role Permissions</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-3">
            {ROLES.map((role) => (
              <div key={role.value} className="p-4 border rounded-lg bg-muted/50">
                <p className="font-semibold mb-3">{role.label}</p>
                <ul className="space-y-1 text-sm text-muted-foreground">
                  {role.permissions.map((perm) => (
                    <li key={perm} className="flex items-start">
                      <span className="mr-2">•</span>
                      {perm}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
