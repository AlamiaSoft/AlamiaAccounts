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
const CURRENCIES = ["INR", "USD", "EUR", "GBP", "AED", "SGD", "BDT", "PKR", "LKR", "THB", "MYR", "PHP"]

export default function AccountMasterForm({ groups, account, onSubmit, onCancel }) {
  const [formData, setFormData] = useState(
    account || {
      code: "",
      name: "",
      alias: "",
      groupId: "",
      type: "Bank",
      balance: 0,
      currency: "INR",
      description: "",
      isActive: true,
      openingBalance: 0,
    },
  )

  const [errors, setErrors] = useState({})

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target
    setFormData((prev) => ({
      ...prev,
      [name]: type === "checkbox" ? checked : value,
    }))
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: "" }))
    }
  }

  const handleSelectChange = (name, value) => {
    setFormData((prev) => ({ ...prev, [name]: value }))
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: "" }))
    }
  }

  const validateForm = () => {
    const newErrors = {}
    if (!formData.code.trim()) newErrors.code = "Account code is required"
    if (!formData.name.trim()) newErrors.name = "Account name is required"
    if (!formData.groupId) newErrors.groupId = "Group is required"
    if (!/^\d+$/.test(formData.code)) newErrors.code = "Account code must contain only numbers"
    return newErrors
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    const newErrors = validateForm()
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors)
      return
    }
    onSubmit(formData)
  }

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
            placeholder="e.g., 1001"
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
            placeholder="e.g., Bank Account"
            className={errors.name ? "border-destructive" : ""}
          />
        </div>
      </div>

      <div>
        <Label htmlFor="alias">Alias / Short Name</Label>
        <Input
          id="alias"
          name="alias"
          value={formData.alias}
          onChange={handleChange}
          placeholder="Optional alias for quick reference"
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <Label htmlFor="groupId">
            Group * {errors.groupId && <span className="text-destructive text-xs">{errors.groupId}</span>}
          </Label>
          <SelectComponent value={formData.groupId} onValueChange={(value) => handleSelectChange("groupId", value)}>
            <SelectTrigger className={errors.groupId ? "border-destructive" : ""}>
              <SelectValue placeholder="Select group" />
            </SelectTrigger>
            <SelectContent>
              {groups.map((group) => (
                <SelectItem key={group.id} value={group.id}>
                  {group.code} - {group.name}
                </SelectItem>
              ))}
            </SelectContent>
          </SelectComponent>
        </div>
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
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <Label htmlFor="openingBalance">Opening Balance</Label>
          <Input
            id="openingBalance"
            name="openingBalance"
            type="number"
            value={formData.openingBalance}
            onChange={handleChange}
            placeholder="0"
            step="0.01"
          />
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
        <Label htmlFor="description">Description</Label>
        <Textarea
          id="description"
          name="description"
          value={formData.description}
          onChange={handleChange}
          placeholder="Account details or notes"
          className="resize-y"
        />
      </div>

      <div className="flex items-center gap-2">
        <input
          id="isActive"
          name="isActive"
          type="checkbox"
          checked={formData.isActive}
          onChange={handleChange}
          className="w-4 h-4 rounded border-input"
        />
        <Label htmlFor="isActive" className="font-normal cursor-pointer">
          Active Account
        </Label>
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
