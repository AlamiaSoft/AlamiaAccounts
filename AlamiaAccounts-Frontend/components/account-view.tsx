"use client"

import { ArrowLeft, Edit, Trash2, FileText, TrendingUp } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"

interface Account {
  id: string
  code: string
  name: string
  groupId: string
  type: string
  balance: number
  currency: string
}

interface AccountViewProps {
  account: Account
  groupName: string
  onBack: () => void
  onEdit: () => void
  onDelete?: () => void
}

export default function AccountView({ account, groupName, onBack, onEdit, onDelete }: AccountViewProps) {
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
            <h1 className="text-3xl font-bold tracking-tight">{account.name}</h1>
            <p className="text-muted-foreground mt-1">
              Account Code: {account.code} • {groupName}
            </p>
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
        {/* Account Details */}
        <Card>
          <CardHeader>
            <CardTitle>Account Details</CardTitle>
            <CardDescription>Basic account information</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-muted-foreground mb-1">Account Code</p>
                <p className="font-medium">{account.code}</p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground mb-1">Account Type</p>
                <Badge variant="outline">{account.type}</Badge>
              </div>
            </div>
            <Separator />
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-muted-foreground mb-1">Group</p>
                <p className="font-medium">{groupName}</p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground mb-1">Currency</p>
                <p className="font-medium">{account.currency}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Balance Information */}
        <Card>
          <CardHeader>
            <CardTitle>Balance Information</CardTitle>
            <CardDescription>Current account balance</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">Current Balance</p>
                <p className="text-2xl font-bold">
                  {account.currency} {account.balance.toLocaleString("en-IN")}
                </p>
              </div>
              <Separator />
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <TrendingUp className="w-4 h-4" />
                <span>Last updated: Today</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Recent Transactions */}
      <Card>
        <CardHeader>
          <CardTitle>Recent Transactions</CardTitle>
          <CardDescription>Latest activity on this account</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-muted-foreground">
            <FileText className="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>No recent transactions</p>
            <p className="text-sm">Transaction history will appear here</p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
