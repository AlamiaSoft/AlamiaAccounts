// Sample data for multiple companies to test search functionality
export interface Voucher {
  id: string
  number: string
  type: "payment" | "receipt" | "journal" | "general"
  date: string
  reference: string
  narration: string
  companyId: string
  amount: number
  lineItems: {
    id: string
    account: string
    accountName: string
    debit: number
    credit: number
    description: string
  }[]
}

export interface Account {
  id: string
  code: string
  name: string
  type: "asset" | "liability" | "income" | "expense" | "equity"
  companyId: string
  balance: number
}

export interface User {
  id: string
  name: string
  email: string
  role: "admin" | "accountant" | "viewer"
  companyId: string
}

export interface Ledger {
  id: string
  accountId: string
  accountName: string
  companyId: string
  transactions: {
    id: string
    date: string
    voucherNumber: string
    description: string
    debit: number
    credit: number
    balance: number
  }[]
}

// Company 1: Acme Corporation
export const acmeVouchers: Voucher[] = [
  {
    id: "v1",
    number: "PV-001",
    type: "payment",
    date: "2025-01-15",
    reference: "INV-2025-001",
    narration: "Payment to supplier for raw materials",
    companyId: "1",
    amount: 50000,
    lineItems: [
      { id: "1", account: "2001", accountName: "Accounts Payable", debit: 50000, credit: 0, description: "" },
      { id: "2", account: "1001", accountName: "Cash in Hand", debit: 0, credit: 50000, description: "" },
    ],
  },
  {
    id: "v2",
    number: "RV-002",
    type: "receipt",
    date: "2025-01-18",
    reference: "INV-C-125",
    narration: "Sales Receipt from ABC Industries",
    companyId: "1",
    amount: 125000,
    lineItems: [
      { id: "1", account: "1001", accountName: "Cash in Hand", debit: 125000, credit: 0, description: "" },
      { id: "2", account: "3001", accountName: "Sales Revenue", debit: 0, credit: 125000, description: "" },
    ],
  },
  {
    id: "v3",
    number: "JV-003",
    type: "journal",
    date: "2025-01-20",
    reference: "ADJ-001",
    narration: "Salary expense adjustment",
    companyId: "1",
    amount: 75000,
    lineItems: [
      { id: "1", account: "4001", accountName: "Salary Expense", debit: 75000, credit: 0, description: "" },
      { id: "2", account: "2002", accountName: "Salary Payable", debit: 0, credit: 75000, description: "" },
    ],
  },
]

export const acmeAccounts: Account[] = [
  { id: "a1", code: "1001", name: "Cash in Hand", type: "asset", companyId: "1", balance: 250000 },
  { id: "a2", code: "3001", name: "Sales Revenue", type: "income", companyId: "1", balance: 500000 },
  { id: "a3", code: "2001", name: "Accounts Payable", type: "liability", companyId: "1", balance: 120000 },
  { id: "a4", code: "4001", name: "Salary Expense", type: "expense", companyId: "1", balance: 150000 },
  { id: "a5", code: "2002", name: "Salary Payable", type: "liability", companyId: "1", balance: 75000 },
]

export const acmeUsers: User[] = [
  { id: "u1", name: "John Smith", email: "john@acme.com", role: "admin", companyId: "1" },
  { id: "u2", name: "Sarah Johnson", email: "sarah@acme.com", role: "accountant", companyId: "1" },
]

// Company 2: TechStart Solutions
export const techstartVouchers: Voucher[] = [
  {
    id: "v4",
    number: "PV-101",
    type: "payment",
    date: "2025-01-12",
    reference: "RENT-JAN",
    narration: "Office rent payment for January",
    companyId: "2",
    amount: 85000,
    lineItems: [
      { id: "1", account: "4101", accountName: "Rent Expense", debit: 85000, credit: 0, description: "" },
      { id: "2", account: "1101", accountName: "Bank Account", debit: 0, credit: 85000, description: "" },
    ],
  },
  {
    id: "v5",
    number: "RV-102",
    type: "receipt",
    date: "2025-01-16",
    reference: "INV-TS-045",
    narration: "Consulting service revenue",
    companyId: "2",
    amount: 200000,
    lineItems: [
      { id: "1", account: "1101", accountName: "Bank Account", debit: 200000, credit: 0, description: "" },
      { id: "2", account: "3101", accountName: "Service Revenue", debit: 0, credit: 200000, description: "" },
    ],
  },
  {
    id: "v6",
    number: "PV-103",
    type: "payment",
    date: "2025-01-22",
    reference: "UTIL-JAN",
    narration: "Utility bills payment",
    companyId: "2",
    amount: 15000,
    lineItems: [
      { id: "1", account: "4102", accountName: "Utilities Expense", debit: 15000, credit: 0, description: "" },
      { id: "2", account: "1101", accountName: "Bank Account", debit: 0, credit: 15000, description: "" },
    ],
  },
]

export const techstartAccounts: Account[] = [
  { id: "a6", code: "1101", name: "Bank Account", type: "asset", companyId: "2", balance: 450000 },
  { id: "a7", code: "3101", name: "Service Revenue", type: "income", companyId: "2", balance: 800000 },
  { id: "a8", code: "4101", name: "Rent Expense", type: "expense", companyId: "2", balance: 85000 },
  { id: "a9", code: "4102", name: "Utilities Expense", type: "expense", companyId: "2", balance: 15000 },
]

export const techstartUsers: User[] = [
  { id: "u3", name: "Mike Chen", email: "mike@techstart.com", role: "admin", companyId: "2" },
  { id: "u4", name: "Emily Davis", email: "emily@techstart.com", role: "viewer", companyId: "2" },
]

// Helper functions to get data by company
export function getAllVouchers(): Voucher[] {
  return [...acmeVouchers, ...techstartVouchers]
}

export function getVouchersByCompany(companyId: string): Voucher[] {
  return getAllVouchers().filter((v) => v.companyId === companyId)
}

export function getVoucherById(id: string): Voucher | undefined {
  return getAllVouchers().find((v) => v.id === id)
}

export function getAllAccounts(): Account[] {
  return [...acmeAccounts, ...techstartAccounts]
}

export function getAccountsByCompany(companyId: string): Account[] {
  return getAllAccounts().filter((a) => a.companyId === companyId)
}

export function getAccountById(id: string): Account | undefined {
  return getAllAccounts().find((a) => a.id === id)
}

export function getAllUsers(): User[] {
  return [...acmeUsers, ...techstartUsers]
}

export function getUsersByCompany(companyId: string): User[] {
  return getAllUsers().filter((u) => u.companyId === companyId)
}

export function getUserById(id: string): User | undefined {
  return getAllUsers().find((u) => u.id === id)
}
