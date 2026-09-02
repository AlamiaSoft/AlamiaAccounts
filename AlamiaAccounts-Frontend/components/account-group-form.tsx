"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"

const PREDEFINED_GROUPS = [
  { name: "Capital Account", code: "CA" },
  { name: "Fixed Assets", code: "FA" },
  { name: "Current Assets", code: "CUA" },
  { name: "Current Liabilities", code: "CL" },
  { name: "Borrowings", code: "BOR" },
  { name: "Income", code: "INC" },
  { name: "Expenses", code: "EXP" },
  { name: "Indirect Expenses", code: "IEXP" },
]

export default function AccountGroupForm({ group, onSubmit, onCancel }) {
  const [formData, setFormData] = useState(
    group || {
      name: "",
      code: "",
      description: "",
    },
  )
  const [errors, setErrors] = useState({})

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: "" }))
    }
  }

  const validateForm = () => {
    const newErrors = {}
    if (!formData.name.trim()) newErrors.name = "Group name is required"
    if (!formData.code.trim()) newErrors.code = "Group code is required"
    if (formData.code.length > 10) newErrors.code = "Group code must be 10 characters or less"
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
      <div>
        <Label htmlFor="code">
          Group Code * {errors.code && <span className="text-destructive text-xs">{errors.code}</span>}
        </Label>
        <Input
          id="code"
          name="code"
          value={formData.code}
          onChange={handleChange}
          placeholder="e.g., CA, FA, CUA"
          maxLength={10}
          className={errors.code ? "border-destructive" : ""}
        />
      </div>
      <div>
        <Label htmlFor="name">
          Group Name * {errors.name && <span className="text-destructive text-xs">{errors.name}</span>}
        </Label>
        <Input
          id="name"
          name="name"
          value={formData.name}
          onChange={handleChange}
          placeholder="e.g., Capital Account"
          className={errors.name ? "border-destructive" : ""}
        />
      </div>
      <div>
        <Label htmlFor="description">Description</Label>
        <Textarea
          id="description"
          name="description"
          value={formData.description}
          onChange={handleChange}
          placeholder="Brief description of this group"
          rows={3}
        />
      </div>
      <div className="flex justify-end gap-2 pt-4">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit">{group ? "Update Group" : "Create Group"}</Button>
      </div>
    </form>
  )
}
