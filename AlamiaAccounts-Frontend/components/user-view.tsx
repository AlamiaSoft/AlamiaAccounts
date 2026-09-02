"use client"

import { ArrowLeft, Edit, Trash2, Shield, Mail, UserCircle } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"

interface User {
  id: string
  name: string
  email: string
  role: "admin" | "accountant" | "viewer"
  status: "active" | "inactive"
}

interface UserViewProps {
  user: User
  onBack: () => void
  onEdit: () => void
  onDelete?: () => void
}

const ROLE_DETAILS = {
  admin: {
    label: "Admin",
    permissions: ["All Permissions", "User Management", "Company Settings", "Full Access"],
    color: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
  },
  accountant: {
    label: "Accountant",
    permissions: ["Transactions", "Reports", "Ledgers", "Chart of Accounts"],
    color: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
  },
  viewer: {
    label: "Viewer",
    permissions: ["View Reports", "View Ledgers", "Read-only Access"],
    color: "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400",
  },
}

export default function UserView({ user, onBack, onEdit, onDelete }: UserViewProps) {
  const roleDetails = ROLE_DETAILS[user.role]

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={onBack}>
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back
          </Button>
          <div>
            <h1 className="text-3xl font-bold tracking-tight">{user.name}</h1>
            <p className="text-muted-foreground mt-1">{user.email}</p>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onEdit}>
            <Edit className="w-4 h-4 mr-2" />
            Edit
          </Button>
          {onDelete && (
            <Button variant="outline" onClick={onDelete}>
              <Trash2 className="w-4 h-4 mr-2 text-destructive" />
              Delete
            </Button>
          )}
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        {/* User Details */}
        <Card>
          <CardHeader>
            <CardTitle>User Information</CardTitle>
            <CardDescription>Basic user details and account status</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center gap-3">
              <UserCircle className="w-10 h-10 text-muted-foreground" />
              <div>
                <p className="font-medium">{user.name}</p>
                <p className="text-sm text-muted-foreground">User ID: {user.id}</p>
              </div>
            </div>
            <Separator />
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Mail className="w-4 h-4" />
                  <span>Email</span>
                </div>
                <p className="font-medium">{user.email}</p>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Shield className="w-4 h-4" />
                  <span>Role</span>
                </div>
                <Badge className={roleDetails.color}>{roleDetails.label}</Badge>
              </div>
              <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">Status</p>
                <Badge
                  variant={user.status === "active" ? "default" : "secondary"}
                  className={
                    user.status === "active"
                      ? "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                      : ""
                  }
                >
                  {user.status}
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Role Permissions */}
        <Card>
          <CardHeader>
            <CardTitle>Role & Permissions</CardTitle>
            <CardDescription>Access level and capabilities</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div>
                <p className="text-sm font-medium mb-2">Current Role</p>
                <Badge className={roleDetails.color}>{roleDetails.label}</Badge>
              </div>
              <Separator />
              <div>
                <p className="text-sm font-medium mb-3">Permissions</p>
                <ul className="space-y-2">
                  {roleDetails.permissions.map((permission) => (
                    <li key={permission} className="flex items-start text-sm">
                      <span className="text-green-500 mr-2">✓</span>
                      <span className="text-muted-foreground">{permission}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Activity Log */}
      <Card>
        <CardHeader>
          <CardTitle>Recent Activity</CardTitle>
          <CardDescription>User activity log</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-muted-foreground">
            <Shield className="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>No recent activity</p>
            <p className="text-sm">Activity history will appear here</p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
