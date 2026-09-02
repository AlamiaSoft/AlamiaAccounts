"use client"

import { useState } from "react"
import { ChevronDown, ChevronRight, Edit, Trash2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Tooltip, TooltipContent, TooltipTrigger, TooltipProvider } from "@/components/ui/tooltip"

export default function AccountTreeView({ accounts, onEditAccount, onDeleteAccount }) {
  return (
    <TooltipProvider>
      <div className="space-y-1">
        {accounts
          .sort((a, b) => a.code.localeCompare(b.code))
          .map((account) => (
            <TreeNode
              key={account.id}
              account={account}
              onEditAccount={onEditAccount}
              onDeleteAccount={onDeleteAccount}
              level={0}
            />
          ))}
      </div>
    </TooltipProvider>
  )
}

function TreeNode({ account, onEditAccount, onDeleteAccount, level }) {
  const [isExpanded, setIsExpanded] = useState(level < 1) // Expand first level by default
  const [copiedAccountCode, setCopiedAccountCode] = useState(null)

  const hasChildren = account.children && account.children.length > 0
  const isCategory = !!account.category

  const copyToClipboard = (text, accountId) => {
    navigator.clipboard.writeText(text)
    setCopiedAccountCode(accountId)
    setTimeout(() => setCopiedAccountCode(null), 2000)
  }

  const getAccountTypeColor = (type) => {
    const colorMap = {
      Bank: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
      Cash: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
      Capital: "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200",
      Income: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200",
      Expense: "bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200",
      Loan: "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200",
      Asset: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200",
      Liability: "bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200",
    }
    return colorMap[type] || "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200"
  }

  return (
    <div className="space-y-1">
      <div
        className={`flex items-center justify-between px-3 py-2 rounded-lg hover:bg-muted transition-colors group/account ${isCategory ? "font-semibold" : ""
          }`}
        style={{ paddingLeft: `${(level * 16) + 12}px` }}
      >
        <div className="flex-1 min-w-0 flex items-center gap-2">
          <div className="flex-shrink-0 w-4">
            {hasChildren && (
              <button onClick={() => setIsExpanded(!isExpanded)} className="p-0.5 hover:bg-muted-foreground/20 rounded">
                {isExpanded ? <ChevronDown className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
              </button>
            )}
          </div>

          <Tooltip>
            <TooltipTrigger asChild>
              <button
                onClick={() => copyToClipboard(account.code, account.id)}
                className="text-xs font-mono text-muted-foreground hover:text-foreground transition-colors"
              >
                {account.code}
              </button>
            </TooltipTrigger>
            <TooltipContent>
              {copiedAccountCode === account.id ? "Copied!" : "Click to copy code"}
            </TooltipContent>
          </Tooltip>

          <span className="text-sm text-foreground truncate">{account.name}</span>
        </div>

        <div className="flex items-center gap-2 ml-2">
          {!isCategory && (
            <Badge className={`text-xs ${getAccountTypeColor(account.type)}`}>{account.type}</Badge>
          )}
          {account.balance !== undefined && (
            <span className="text-sm font-medium text-right min-w-max">
              {account.balance.toLocaleString("en-IN")}
            </span>
          )}

          <div className="opacity-0 group-hover/account:opacity-100 transition-opacity flex gap-1 flex-shrink-0">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => onEditAccount(account)}
                  className="h-7 w-7 p-0"
                >
                  <Edit className="w-3.5 h-3.5" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Edit</TooltipContent>
            </Tooltip>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => onDeleteAccount(account.id)}
                  className="h-7 w-7 p-0"
                >
                  <Trash2 className="w-3.5 h-3.5 text-destructive" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Delete</TooltipContent>
            </Tooltip>
          </div>
        </div>
      </div>

      {isExpanded && hasChildren && (
        <div className="space-y-1">
          {account.children
            .sort((a, b) => a.code.localeCompare(b.code))
            .map((child) => (
              <TreeNode
                key={child.id}
                account={child}
                onEditAccount={onEditAccount}
                onDeleteAccount={onDeleteAccount}
                level={level + 1}
              />
            ))}
        </div>
      )}
    </div>
  )
}
