export interface AccountOption {
  code: string
  name: string
  category: boolean
  type?: string
  groupId?: string
  parent_code?: string
  parent_name?: string
}

export interface AccountRule {
  id?: string | number
  side: "debit" | "credit"
  account_groups?: string[]
  accountGroups?: string[]
  default_account?: string
  defaultAccount?: string
}

/**
 * Checks whether an account belongs to a given account group name.
 */
export function isAccountInGroup(acc: any, groupName: string): boolean {
  if (!acc || !groupName) return false
  const g = groupName.toLowerCase().trim()
  const name = (acc.name || "").toLowerCase()
  const code = String(acc.code || "")
  const type = (acc.type || "").toLowerCase()
  const groupId = (acc.groupId || "").toLowerCase()
  const parentName = (acc.parent_name || "").toLowerCase()
  const parentCode = String(acc.parent_code || "")

  if (g === "cash") {
    return name.includes("cash") || code.startsWith("111") || parentName.includes("cash")
  }
  if (g === "bank accounts" || g === "bank" || g === "banks") {
    return name.includes("bank") || code.startsWith("112") || code.startsWith("113") || parentName.includes("bank")
  }
  if (g === "accounts receivable" || g === "receivable" || g === "receivables" || g === "debtors") {
    return name.includes("receivable") || code.startsWith("12") || parentName.includes("receivable")
  }
  if (g === "accounts payable" || g === "payable" || g === "payables" || g === "creditors") {
    return name.includes("payable") || code.startsWith("21") || parentName.includes("payable")
  }
  if (g === "fixed assets" || g === "non-current assets") {
    return (
      (type === "asset" || groupId === "asset") &&
      (code.startsWith("15") ||
        code.startsWith("16") ||
        name.includes("fixed") ||
        name.includes("equipment") ||
        name.includes("vehicle") ||
        name.includes("building") ||
        name.includes("land") ||
        name.includes("furniture") ||
        name.includes("machinery"))
    )
  }
  if (g === "expenses" || g === "expense") {
    return type === "expense" || groupId === "expense" || code.startsWith("4") || parentName.includes("expense")
  }
  if (g === "cost of goods sold" || g === "cogs") {
    return code.startsWith("41") || name.includes("cost of goods") || name.includes("cogs")
  }
  if (g === "revenue" || g === "income" || g === "sales") {
    return (
      type === "income" ||
      groupId === "income" ||
      code.startsWith("50") ||
      name.includes("revenue") ||
      name.includes("sales") ||
      parentName.includes("revenue")
    )
  }
  if (g === "fee income") {
    return (type === "income" || groupId === "income") && name.includes("fee")
  }
  if (g === "capital" || g === "equity") {
    return (
      type === "capital" ||
      type === "equity" ||
      groupId === "capital" ||
      groupId === "equity" ||
      code.startsWith("51") ||
      code.startsWith("52") ||
      code.startsWith("53") ||
      code.startsWith("3")
    )
  }

  // Fallback matching
  return (
    name.includes(g) ||
    type.includes(g) ||
    groupId.includes(g) ||
    parentName.includes(g) ||
    parentCode === g
  )
}

/**
 * Filter accounts based on account rules for a given side ('debit' | 'credit' | 'all')
 */
export function getFilteredAccountsByRules(
  accounts: AccountOption[],
  rules: AccountRule[] | undefined | null,
  side: "debit" | "credit" | "all"
): AccountOption[] {
  if (!rules || rules.length === 0) {
    return accounts
  }

  const matchingRules = rules.filter((r) => side === "all" || r.side === side)
  if (matchingRules.length === 0) {
    return accounts
  }

  const allowedGroups: string[] = []
  matchingRules.forEach((r) => {
    const rawGroups = r.account_groups || r.accountGroups
    const groups = Array.isArray(rawGroups)
      ? rawGroups
      : typeof rawGroups === "string"
      ? JSON.parse(rawGroups || "[]")
      : []
    groups.forEach((g: string) => {
      if (g && !allowedGroups.includes(g)) {
        allowedGroups.push(g)
      }
    })
  })

  // If rules exist but no specific groups specified, allow all accounts
  if (allowedGroups.length === 0) {
    return accounts
  }

  return accounts.filter((acc) => {
    // Hide folder categories when filtering to allowed leaf accounts
    if (acc.category) return false
    return allowedGroups.some((group) => isAccountInGroup(acc, group))
  })
}
