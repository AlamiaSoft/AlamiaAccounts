"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select as SelectComponent,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"

const ACCOUNT_TYPES = [
  "Bank",
  "Cash",
  "Capital",
  "Loan",
  "Income",
  "Expense",
  "Asset",
  "Liability",
  "Equity",
  "Receivable",
  "Payable",
]
const CURRENCIES = ["PKR", "USD", "EUR", "GBP", "AED", "SAR", "INR"]

export default function AccountMasterForm({ groups, account, availableAccounts = [], onSubmit, onCancel }: any) {
  const [formData, setFormData] = useState(
    account || {
      code: "",
      name: "",
      alias: "",
      groupId: "current",
      parent_code: "1000",
      type: "Bank",
      balanceSide: "debit", // 'debit' or 'credit'
      currency: "PKR",
      category: false,
      description: "",
      isActive: true,
      openingBalance: 0,
    },
  )

  const [errors, setErrors] = useState<Record<string, string>>({})

  const handleChange = (e: any) => {
    const { name, value, type, checked } = e.target
    setFormData((prev: any) => ({
      ...prev,
      [name]: type === "checkbox" ? checked : value,
    }))
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: "" }))
    }
  }

  const handleSelectChange = (name: string, value: string) => {
    setFormData((prev: any) => {
      const next = { ...prev, [name]: value }
      // Auto adjust balanceSide and parent based on type
      if (name === "type") {
        if (["Bank", "Cash", "Asset", "Expense", "Receivable"].includes(value)) {
          next.balanceSide = "debit"
        } else {
          next.balanceSide = "credit"
        }
      }
      return next
    })
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: "" }))
    }
  }

  const validateForm = () => {
    const newErrors: Record<string, string> = {}
    if (!formData.code.trim()) newErrors.code = "Account code is required"
    if (!formData.name.trim()) newErrors.name = "Account name is required"
    if (!/^\d+$/.test(formData.code)) newErrors.code = "Account code must contain only numbers"
    return newErrors
  }

  const handleSubmit = (e: any) => {
    e.preventDefault()
    const newErrors = validateForm()
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors)
      return
    }

    const payload = {
      ...formData,
      debit: formData.balanceSide === "debit",
      credit: formData.balanceSide === "credit",
      category: Boolean(formData.category),
      parent_code: formData.parent_code || null,
    }

    onSubmit(payload)
  }

  // Filter potential parent accounts (categories or existing accounts)
  const parentOptions = (availableAccounts || []).filter((acc: any) => acc.code !== formData.code)

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div>
          <Label htmlFor="code">
            Account Code * {errors.code && <span className="text-destructive text-xs">{errors.code}</span>}
          </Label>
          <Input
            id="code"
            name="code"
            value={formData.code}
            onChange={handleChange}
            placeholder="e.g., 1130"
            maxLength={10}
            className={errors.code ? "border-destructive" : ""}
          />
        </div>
        <div>
          <Label htmlFor="name">
            Account Name * {errors.name && <span className="text-destructive text-xs">{errors.name}</span>}
          </Label>
          <Input
            id="name"
            name="name"
            value={formData.name}
            onChange={handleChange}
            placeholder="e.g., Meezan Bank"
            className={errors.name ? "border-destructive" : ""}
          />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <Label htmlFor="parent_code">Parent Account / Category</Label>
          <SelectComponent
            value={formData.parent_code || ""}
            onValueChange={(value) => handleSelectChange("parent_code", value)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select parent account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">No Parent (Top Category)</SelectItem>
              {parentOptions.map((acc: any) => (
                <SelectItem key={acc.code} value={acc.code}>
                  {acc.code} - {acc.name} {acc.category ? "(Category)" : ""}
                </SelectItem>
              ))}
            </SelectContent>
          </SelectComponent>
        </div>

        <div>
          <Label htmlFor="balanceSide">Normal Balance *</Label>
          <SelectComponent
            value={formData.balanceSide}
            onValueChange={(value) => handleSelectChange("balanceSide", value)}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="debit">Debit Normal (Assets & Expenses)</SelectItem>
              <SelectItem value="credit">Credit Normal (Liabilities, Equity & Revenue)</SelectItem>
            </SelectContent>
          </SelectComponent>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <Label htmlFor="type">Account Type *</Label>
          <SelectComponent value={formData.type} onValueChange={(value) => handleSelectChange("type", value)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {ACCOUNT_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {type}
                </SelectItem>
              ))}
            </SelectContent>
          </SelectComponent>
        </div>

        <div>
          <Label htmlFor="currency">Currency</Label>
          <SelectComponent value={formData.currency} onValueChange={(value) => handleSelectChange("currency", value)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {CURRENCIES.map((curr) => (
                <SelectItem key={curr} value={curr}>
                  {curr}
                </SelectItem>
              ))}
            </SelectContent>
          </SelectComponent>
        </div>
      </div>

      <div>
        <Label htmlFor="description">Description / Notes</Label>
        <Textarea
          id="description"
          name="description"
          value={formData.description || ""}
          onChange={handleChange}
          placeholder="Account details or notes"
          className="resize-y"
        />
      </div>

      <div className="flex items-center justify-between pt-2">
        <div className="flex items-center gap-2">
          <input
            id="category"
            name="category"
            type="checkbox"
            checked={Boolean(formData.category)}
            onChange={handleChange}
            className="w-4 h-4 rounded border-input"
          />
          <Label htmlFor="category" className="font-normal cursor-pointer text-sm">
            Is Folder / Category (Non-posting parent)
          </Label>
        </div>

        <div className="flex items-center gap-2">
          <input
            id="isActive"
            name="isActive"
            type="checkbox"
            checked={Boolean(formData.isActive)}
            onChange={handleChange}
            className="w-4 h-4 rounded border-input"
          />
          <Label htmlFor="isActive" className="font-normal cursor-pointer text-sm">
            Active Account
          </Label>
        </div>
      </div>

      <div className="flex justify-end gap-2 pt-4">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit">{account ? "Update Account" : "Create Account"}</Button>
      </div>
    </form>
  )
}
