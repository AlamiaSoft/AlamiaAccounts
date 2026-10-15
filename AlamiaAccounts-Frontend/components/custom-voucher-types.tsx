"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Plus, Edit, Trash2, Save, X } from "lucide-react"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Checkbox } from "@/components/ui/checkbox"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useVoucherTypes } from "@/hooks/use-voucher-types"
import { useAccounts } from "@/hooks/use-accounts"
import { useToast } from "@/hooks/use-toast"
import { Loader2 } from "lucide-react"

interface CustomField {
  id: string
  name: string
  type: "text" | "number" | "date" | "dropdown" | "multiselect" | "checkbox" | "textarea"
  required: boolean
  options?: string[]
}

interface AccountRule {
  id: string
  side: "debit" | "credit"
  accountGroups: string[]
  defaultAccount?: string
}

interface ValidationRule {
  id: string
  fieldName: string
  type: "required" | "min_value" | "max_value" | "date_range" | "regex"
  value?: string
  message?: string
}

interface AutoCalculationRule {
  id: string
  targetField: string
  formula: string
  description: string
}

interface DefaultValueRule {
  id: string
  fieldName: string
  condition?: string
  defaultValue: string
}

interface ApprovalRule {
  id: string
  condition: string
  approverRole: string
  minAmount?: number
}

interface NumberingScheme {
  startingNumber: number
  padding: number
  separator: string
  customSeparator?: string
  includeYear: boolean
  includeMonth: boolean
  resetPeriod: "never" | "yearly" | "monthly"
}

interface CustomVoucherType {
  id: string
  name: string
  prefix: string
  company_code?: string
  description: string
  customFields: CustomField[]
  accountRules: AccountRule[]
  validationRules: ValidationRule[]
  autoCalculationRules: AutoCalculationRule[]
  defaultValueRules: DefaultValueRule[]
  approvalRules: ApprovalRule[]
  numberingScheme: NumberingScheme
  active: boolean
}

export default function CustomVoucherTypes() {
  const { voucherTypes: apiVoucherTypes, isLoading, createVoucherType, updateVoucherType, deleteVoucherType } = useVoucherTypes()
  const { accounts } = useAccounts()
  const { toast } = useToast()

  const voucherTypes = (apiVoucherTypes || []).map((vt: any) => ({
    id: String(vt.id),
    name: vt.name,
    prefix: vt.prefix,
    company_code: vt.company_code,
    description: vt.description || "",
    customFields: Array.isArray(vt.custom_fields) ? vt.custom_fields.map((f: any) => ({
      id: String(f.id || Math.random()),
      name: f.name,
      type: f.type || "text",
      required: Boolean(f.required),
      options: f.options,
    })) : [],
    accountRules: Array.isArray(vt.account_rules) ? vt.account_rules.map((r: any) => ({
      id: String(r.id || Math.random()),
      side: r.side,
      accountGroups: Array.isArray(r.account_groups) ? r.account_groups : (typeof r.account_groups === 'string' ? JSON.parse(r.account_groups || '[]') : []),
      defaultAccount: r.default_account || "",
    })) : [],
    validationRules: Array.isArray(vt.validation_rules) ? vt.validation_rules.map((v: any) => ({
      id: String(v.id || Math.random()),
      fieldName: v.field_name || v.fieldName || "",
      type: v.type,
      value: v.value,
      message: v.message,
    })) : [],
    autoCalculationRules: Array.isArray(vt.auto_calculation_rules) ? vt.auto_calculation_rules : [],
    defaultValueRules: Array.isArray(vt.default_value_rules) ? vt.default_value_rules : [],
    approvalRules: Array.isArray(vt.approval_rules) ? vt.approval_rules : [],
    numberingScheme: vt.numbering_scheme ? {
      startingNumber: vt.numbering_scheme.starting_number || 1,
      padding: vt.numbering_scheme.padding || 4,
      separator: vt.numbering_scheme.separator || "-",
      includeYear: Boolean(vt.numbering_scheme.include_year),
      includeMonth: Boolean(vt.numbering_scheme.include_month),
      resetPeriod: vt.numbering_scheme.reset_period || "never",
    } : {
      startingNumber: 1,
      padding: 4,
      separator: "-",
      includeYear: false,
      includeMonth: false,
      resetPeriod: "never",
    },
    active: Boolean(vt.active),
  }))

  const [isCreating, setIsCreating] = useState(false)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [formData, setFormData] = useState<Partial<CustomVoucherType>>({
    name: "",
    prefix: "",
    description: "",
    customFields: [],
    accountRules: [],
    validationRules: [],
    autoCalculationRules: [],
    defaultValueRules: [],
    approvalRules: [],
    numberingScheme: {
      startingNumber: 1,
      padding: 4,
      separator: "-",
      includeYear: false,
      includeMonth: false,
      resetPeriod: "never",
    },
    active: true,
  })

  const accountGroups = [
    "Cash",
    "Bank Accounts",
    "Accounts Receivable",
    "Accounts Payable",
    "Fixed Assets",
    "Revenue",
    "Fee Income",
    "Expenses",
    "Cost of Goods Sold",
  ]

  const approverRoles = ["Manager", "Accountant", "Director", "Principal", "CFO", "CEO"]

  const handleSave = async () => {
    if (!formData.name?.trim()) {
      toast({
        title: "Validation Error",
        description: "Voucher Type Name is required.",
        variant: "destructive",
      })
      return
    }
    if (!formData.prefix?.trim()) {
      toast({
        title: "Validation Error",
        description: "Prefix is required.",
        variant: "destructive",
      })
      return
    }

    setIsSaving(true)
    try {
      const payload = {
        name: formData.name.trim(),
        prefix: formData.prefix.trim().toUpperCase(),
        description: formData.description?.trim() || "",
        active: formData.active ?? true,
        custom_fields: (formData.customFields || []).map((f) => ({
          name: f.name,
          type: f.type,
          required: Boolean(f.required),
          options: f.options || null,
        })),
        account_rules: (formData.accountRules || []).map((r) => ({
          side: r.side,
          account_groups: r.accountGroups || [],
          default_account: r.defaultAccount || null,
        })),
        validation_rules: (formData.validationRules || []).map((v) => ({
          field_name: v.fieldName,
          type: v.type,
          value: v.value,
          message: v.message,
        })),
        numbering_scheme: formData.numberingScheme ? {
          starting_number: formData.numberingScheme.startingNumber,
          padding: formData.numberingScheme.padding,
          separator: formData.numberingScheme.separator,
          include_year: formData.numberingScheme.includeYear,
          include_month: formData.numberingScheme.includeMonth,
          reset_period: formData.numberingScheme.resetPeriod,
        } : null,
      }

      if (editingId) {
        await updateVoucherType.mutateAsync({ id: parseInt(editingId), data: payload })
        toast({
          title: "Voucher Type Updated",
          description: `Custom Voucher "${formData.name}" has been updated.`,
        })
      } else {
        await createVoucherType.mutateAsync(payload)
        toast({
          title: "Voucher Type Created 🎉",
          description: `Custom Voucher "${formData.name}" has been created and saved.`,
        })
      }

      setIsCreating(false)
      setEditingId(null)
      setFormData({
        name: "",
        prefix: "",
        description: "",
        customFields: [],
        accountRules: [],
        validationRules: [],
        autoCalculationRules: [],
        defaultValueRules: [],
        approvalRules: [],
        numberingScheme: {
          startingNumber: 1,
          padding: 4,
          separator: "-",
          includeYear: false,
          includeMonth: false,
          resetPeriod: "never",
        },
        active: true,
      })
    } catch (err: any) {
      console.error("Save custom voucher type error:", err)
      toast({
        title: "Save Failed",
        description: err?.response?.data?.error || err?.message || "Failed to save custom voucher type.",
        variant: "destructive",
      })
    } finally {
      setIsSaving(false)
    }
  }

  const handleEdit = (voucherType: CustomVoucherType) => {
    setFormData(voucherType)
    setEditingId(voucherType.id)
    setIsCreating(true)
  }

  const handleDelete = async (id: string) => {
    try {
      await deleteVoucherType.mutateAsync(parseInt(id))
      toast({
        title: "Voucher Type Deleted",
        description: "Custom voucher type has been removed.",
      })
    } catch (err: any) {
      toast({
        title: "Delete Failed",
        description: err?.message || "Failed to delete voucher type.",
        variant: "destructive",
      })
    }
  }

  const addCustomField = () => {
    const newField: CustomField = {
      id: Date.now().toString(),
      name: "",
      type: "text",
      required: false,
    }
    setFormData({
      ...formData,
      customFields: [...(formData.customFields || []), newField],
    })
  }

  const updateCustomField = (id: string, updates: Partial<CustomField>) => {
    setFormData({
      ...formData,
      customFields: formData.customFields?.map((field) => (field.id === id ? { ...field, ...updates } : field)),
    })
  }

  const removeCustomField = (id: string) => {
    setFormData({
      ...formData,
      customFields: formData.customFields?.filter((field) => field.id !== id),
    })
  }

  const addAccountRule = () => {
    console.log("[v0] Adding account rule")
    const newRule: AccountRule = {
      id: Date.now().toString(),
      side: "debit",
      accountGroups: [],
      defaultAccount: "",
    }
    setFormData({
      ...formData,
      accountRules: [...(formData.accountRules || []), newRule],
    })
    console.log("[v0] Account rule added, total rules:", (formData.accountRules || []).length + 1)
  }

  const updateAccountRule = (id: string, updates: Partial<AccountRule>) => {
    setFormData({
      ...formData,
      accountRules: formData.accountRules?.map((rule) => (rule.id === id ? { ...rule, ...updates } : rule)),
    })
  }

  const removeAccountRule = (id: string) => {
    setFormData({
      ...formData,
      accountRules: formData.accountRules?.filter((rule) => rule.id !== id),
    })
  }

  const toggleAccountGroup = (ruleId: string, group: string) => {
    const rule = formData.accountRules?.find((r) => r.id === ruleId)
    if (!rule) return

    const currentGroups = rule.accountGroups
    const newGroups = currentGroups.includes(group)
      ? currentGroups.filter((g) => g !== group)
      : [...currentGroups, group]

    updateAccountRule(ruleId, { accountGroups: newGroups })
  }

  const addValidationRule = () => {
    const newRule: ValidationRule = {
      id: Date.now().toString(),
      fieldName: "",
      type: "required",
      message: "",
    }
    setFormData({
      ...formData,
      validationRules: [...(formData.validationRules || []), newRule],
    })
  }

  const updateValidationRule = (id: string, updates: Partial<ValidationRule>) => {
    setFormData({
      ...formData,
      validationRules: formData.validationRules?.map((rule) => (rule.id === id ? { ...rule, ...updates } : rule)),
    })
  }

  const removeValidationRule = (id: string) => {
    setFormData({
      ...formData,
      validationRules: formData.validationRules?.filter((rule) => rule.id !== id),
    })
  }

  const addAutoCalculationRule = () => {
    const newRule: AutoCalculationRule = {
      id: Date.now().toString(),
      targetField: "",
      formula: "",
      description: "",
    }
    setFormData({
      ...formData,
      autoCalculationRules: [...(formData.autoCalculationRules || []), newRule],
    })
  }

  const updateAutoCalculationRule = (id: string, updates: Partial<AutoCalculationRule>) => {
    setFormData({
      ...formData,
      autoCalculationRules: formData.autoCalculationRules?.map((rule) =>
        rule.id === id ? { ...rule, ...updates } : rule,
      ),
    })
  }

  const removeAutoCalculationRule = (id: string) => {
    setFormData({
      ...formData,
      autoCalculationRules: formData.autoCalculationRules?.filter((rule) => rule.id !== id),
    })
  }

  const addDefaultValueRule = () => {
    const newRule: DefaultValueRule = {
      id: Date.now().toString(),
      fieldName: "",
      defaultValue: "",
    }
    setFormData({
      ...formData,
      defaultValueRules: [...(formData.defaultValueRules || []), newRule],
    })
  }

  const updateDefaultValueRule = (id: string, updates: Partial<DefaultValueRule>) => {
    setFormData({
      ...formData,
      defaultValueRules: formData.defaultValueRules?.map((rule) => (rule.id === id ? { ...rule, ...updates } : rule)),
    })
  }

  const removeDefaultValueRule = (id: string) => {
    setFormData({
      ...formData,
      defaultValueRules: formData.defaultValueRules?.filter((rule) => rule.id !== id),
    })
  }

  const addApprovalRule = () => {
    const newRule: ApprovalRule = {
      id: Date.now().toString(),
      condition: "",
      approverRole: "",
    }
    setFormData({
      ...formData,
      approvalRules: [...(formData.approvalRules || []), newRule],
    })
  }

  const updateApprovalRule = (id: string, updates: Partial<ApprovalRule>) => {
    setFormData({
      ...formData,
      approvalRules: formData.approvalRules?.map((rule) => (rule.id === id ? { ...rule, ...updates } : rule)),
    })
  }

  const removeApprovalRule = (id: string) => {
    setFormData({
      ...formData,
      approvalRules: formData.approvalRules?.filter((rule) => rule.id !== id),
    })
  }

  if (isCreating) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-3xl font-bold">{editingId ? "Edit" : "Create"} Custom Voucher Type</h2>
            <p className="text-muted-foreground mt-1">Define custom voucher types with specific fields and rules</p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setIsCreating(false)}>
              <X className="w-4 h-4 mr-2" />
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={isSaving} className="min-w-[150px]">
              {isSaving ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Save className="w-4 h-4 mr-2" />}
              Save Voucher Type
            </Button>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Basic Information</CardTitle>
            <CardDescription>Define the name and prefix for your custom voucher type</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Voucher Type Name *</Label>
                <Input
                  placeholder="e.g., School Fees Voucher"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>Prefix *</Label>
                <Input
                  placeholder="e.g., SF"
                  value={formData.prefix}
                  onChange={(e) => setFormData({ ...formData, prefix: e.target.value })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Description</Label>
              <Textarea
                placeholder="Describe the purpose of this voucher type"
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Voucher Numbering Scheme</CardTitle>
            <CardDescription>Configure how voucher numbers are generated for this type</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Starting Number</Label>
                <Input
                  type="number"
                  placeholder="1"
                  min="1"
                  value={formData.numberingScheme?.startingNumber || 1}
                  onChange={(e) =>
                    setFormData({
                      ...formData,
                      numberingScheme: {
                        ...formData.numberingScheme!,
                        startingNumber: Math.max(1, Number.parseInt(e.target.value) || 1),
                      },
                    })
                  }
                />
              </div>
              <div className="space-y-2">
                <Label>Number Padding (0-10)</Label>
                <Input
                  type="number"
                  placeholder="4"
                  min="0"
                  max="10"
                  value={formData.numberingScheme?.padding || 4}
                  onChange={(e) => {
                    const value = Number.parseInt(e.target.value) || 0
                    setFormData({
                      ...formData,
                      numberingScheme: {
                        ...formData.numberingScheme!,
                        padding: Math.min(10, Math.max(0, value)),
                      },
                    })
                  }}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Separator</Label>
                <Select
                  value={formData.numberingScheme?.separator || "-"}
                  onValueChange={(value) =>
                    setFormData({
                      ...formData,
                      numberingScheme: { ...formData.numberingScheme!, separator: value },
                    })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="-">Hyphen (-)</SelectItem>
                    <SelectItem value="/">Slash (/)</SelectItem>
                    <SelectItem value="_">Underscore (_)</SelectItem>
                    <SelectItem value=".">Dot (.)</SelectItem>
                    <SelectItem value="none">None</SelectItem>
                    <SelectItem value="custom">Custom</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              {formData.numberingScheme?.separator === "custom" && (
                <div className="space-y-2">
                  <Label>Custom Separator</Label>
                  <Input
                    placeholder="e.g., ::"
                    maxLength={3}
                    value={formData.numberingScheme?.customSeparator || ""}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        numberingScheme: {
                          ...formData.numberingScheme!,
                          customSeparator: e.target.value,
                        },
                      })
                    }
                  />
                </div>
              )}
              <div className="space-y-2">
                <Label>Reset Period</Label>
                <Select
                  value={formData.numberingScheme?.resetPeriod || "never"}
                  onValueChange={(value: "never" | "yearly" | "monthly") =>
                    setFormData({
                      ...formData,
                      numberingScheme: { ...formData.numberingScheme!, resetPeriod: value },
                    })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="never">Never</SelectItem>
                    <SelectItem value="yearly">Yearly</SelectItem>
                    <SelectItem value="monthly">Monthly</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="flex gap-6">
              <div className="flex items-center gap-2">
                <Checkbox
                  checked={formData.numberingScheme?.includeYear || false}
                  onCheckedChange={(checked) =>
                    setFormData({
                      ...formData,
                      numberingScheme: { ...formData.numberingScheme!, includeYear: checked as boolean },
                    })
                  }
                />
                <Label>Include Year (YYYY)</Label>
              </div>
              <div className="flex items-center gap-2">
                <Checkbox
                  checked={formData.numberingScheme?.includeMonth || false}
                  onCheckedChange={(checked) =>
                    setFormData({
                      ...formData,
                      numberingScheme: { ...formData.numberingScheme!, includeMonth: checked as boolean },
                    })
                  }
                />
                <Label>Include Month (MM)</Label>
              </div>
            </div>
            <div className="p-3 bg-muted rounded-md">
              <Label className="text-sm font-medium">Preview:</Label>
              <p className="text-sm text-muted-foreground mt-1">
                {formData.prefix}
                {formData.numberingScheme?.separator === "none"
                  ? ""
                  : formData.numberingScheme?.separator === "custom"
                    ? formData.numberingScheme?.customSeparator || ""
                    : formData.numberingScheme?.separator}
                {formData.numberingScheme?.includeYear && "2024"}
                {formData.numberingScheme?.includeYear &&
                  formData.numberingScheme?.includeMonth &&
                  (formData.numberingScheme?.separator === "none"
                    ? ""
                    : formData.numberingScheme?.separator === "custom"
                      ? formData.numberingScheme?.customSeparator || ""
                      : formData.numberingScheme?.separator)}
                {formData.numberingScheme?.includeMonth && "11"}
                {(formData.numberingScheme?.includeYear || formData.numberingScheme?.includeMonth) &&
                  (formData.numberingScheme?.separator === "none"
                    ? ""
                    : formData.numberingScheme?.separator === "custom"
                      ? formData.numberingScheme?.customSeparator || ""
                      : formData.numberingScheme?.separator)}
                {String(formData.numberingScheme?.startingNumber || 1).padStart(
                  formData.numberingScheme?.padding || 4,
                  "0",
                )}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>Custom Fields</CardTitle>
                <CardDescription>Add custom fields specific to this voucher type</CardDescription>
              </div>
              <Button size="sm" onClick={addCustomField}>
                <Plus className="w-4 h-4 mr-2" />
                Add Field
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {formData.customFields?.map((field) => (
              <div key={field.id} className="flex flex-col gap-3 p-4 border rounded-lg">
                <div className="flex items-end gap-4">
                  <div className="flex-1 space-y-2">
                    <Label>Field Name</Label>
                    <Input
                      placeholder="e.g., Student ID"
                      value={field.name}
                      onChange={(e) => updateCustomField(field.id, { name: e.target.value })}
                    />
                  </div>
                  <div className="w-[200px] space-y-2">
                    <Label>Field Type</Label>
                    <Select
                      value={field.type}
                      onValueChange={(value: any) => updateCustomField(field.id, { type: value })}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="text">Text</SelectItem>
                        <SelectItem value="number">Number</SelectItem>
                        <SelectItem value="date">Date</SelectItem>
                        <SelectItem value="dropdown">Dropdown</SelectItem>
                        <SelectItem value="multiselect">Multi-Select</SelectItem>
                        <SelectItem value="checkbox">Checkbox</SelectItem>
                        <SelectItem value="textarea">Text Area</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="flex items-center gap-2">
                    <Checkbox
                      checked={field.required}
                      onCheckedChange={(checked) => updateCustomField(field.id, { required: checked as boolean })}
                    />
                    <Label>Required</Label>
                  </div>
                  <Button variant="ghost" size="icon" onClick={() => removeCustomField(field.id)}>
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>
                {(field.type === "dropdown" || field.type === "multiselect") && (
                  <div className="space-y-2">
                    <Label>Options (comma-separated)</Label>
                    <Input
                      placeholder="e.g., Option 1, Option 2, Option 3"
                      value={field.options?.join(", ") || ""}
                      onChange={(e) =>
                        updateCustomField(field.id, {
                          options: e.target.value
                            .split(",")
                            .map((opt) => opt.trim())
                            .filter(Boolean),
                        })
                      }
                    />
                  </div>
                )}
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Rules & Validations</CardTitle>
            <CardDescription>Configure business rules and validations for this voucher type</CardDescription>
          </CardHeader>
          <CardContent>
            <Tabs defaultValue="accounts">
              <TabsList className="grid w-full grid-cols-5">
                <TabsTrigger value="accounts">Account Rules</TabsTrigger>
                <TabsTrigger value="validation">Validation</TabsTrigger>
                <TabsTrigger value="calculation">Auto-Calculation</TabsTrigger>
                <TabsTrigger value="defaults">Defaults</TabsTrigger>
                <TabsTrigger value="approval">Approval</TabsTrigger>
              </TabsList>

              <TabsContent value="accounts" className="space-y-4 mt-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">
                    Define which account groups can be used for debit and credit
                  </p>
                  <Button size="sm" onClick={addAccountRule}>
                    <Plus className="w-4 h-4 mr-2" />
                    Add Rule
                  </Button>
                </div>
                {formData.accountRules?.map((rule) => (
                  <div key={rule.id} className="p-4 border rounded-lg space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div className="flex flex-wrap items-center gap-4">
                        <Select
                          value={rule.side}
                          onValueChange={(value: "debit" | "credit") => updateAccountRule(rule.id, { side: value })}
                        >
                          <SelectTrigger className="w-[120px]">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="debit">Debit</SelectItem>
                            <SelectItem value="credit">Credit</SelectItem>
                          </SelectContent>
                        </Select>

                        <div className="flex items-center gap-2">
                          <Label className="text-xs text-muted-foreground whitespace-nowrap">Default Account:</Label>
                          <Select
                            value={rule.defaultAccount || "none"}
                            onValueChange={(val) => updateAccountRule(rule.id, { defaultAccount: val === "none" ? "" : val })}
                          >
                            <SelectTrigger className="w-[240px]">
                              <SelectValue placeholder="No default" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="none">-- No default --</SelectItem>
                              {(accounts || []).filter((a: any) => !a.category).map((a: any) => (
                                <SelectItem key={a.code} value={a.code}>
                                  [{a.code}] {a.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>

                        <span className="text-xs text-muted-foreground">
                          {rule.accountGroups.length} group(s) selected
                        </span>
                      </div>
                      <Button variant="ghost" size="icon" onClick={() => removeAccountRule(rule.id)}>
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                      {accountGroups.map((group) => (
                        <div key={group} className="flex items-center gap-2">
                          <Checkbox
                            checked={rule.accountGroups.includes(group)}
                            onCheckedChange={() => toggleAccountGroup(rule.id, group)}
                          />
                          <Label className="text-sm">{group}</Label>
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </TabsContent>

              <TabsContent value="validation" className="space-y-4 mt-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">Set validation rules for custom fields</p>
                  <Button size="sm" onClick={addValidationRule}>
                    <Plus className="w-4 h-4 mr-2" />
                    Add Validation
                  </Button>
                </div>
                {formData.validationRules?.map((rule) => (
                  <div key={rule.id} className="p-4 border rounded-lg space-y-3">
                    <div className="flex items-start gap-4">
                      <div className="flex-1 space-y-2">
                        <Label>Field Name</Label>
                        <Select
                          value={rule.fieldName}
                          onValueChange={(value) => updateValidationRule(rule.id, { fieldName: value })}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Select field" />
                          </SelectTrigger>
                          <SelectContent>
                            {formData.customFields?.map((field) => (
                              <SelectItem key={field.id} value={field.name}>
                                {field.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex-1 space-y-2">
                        <Label>Validation Type</Label>
                        <Select
                          value={rule.type}
                          onValueChange={(value: any) => updateValidationRule(rule.id, { type: value })}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="required">Required</SelectItem>
                            <SelectItem value="min_value">Min Value</SelectItem>
                            <SelectItem value="max_value">Max Value</SelectItem>
                            <SelectItem value="date_range">Date Range</SelectItem>
                            <SelectItem value="regex">Regex Pattern</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="mt-8"
                        onClick={() => removeValidationRule(rule.id)}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      {rule.type !== "required" && (
                        <div className="space-y-2">
                          <Label>Value</Label>
                          <Input
                            placeholder="e.g., 100 or ^[A-Z]{2}\\d{4}$"
                            value={rule.value || ""}
                            onChange={(e) => updateValidationRule(rule.id, { value: e.target.value })}
                          />
                        </div>
                      )}
                      <div className="space-y-2">
                        <Label>Error Message</Label>
                        <Input
                          placeholder="e.g., This field is mandatory"
                          value={rule.message || ""}
                          onChange={(e) => updateValidationRule(rule.id, { message: e.target.value })}
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </TabsContent>

              <TabsContent value="calculation" className="space-y-4 mt-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">
                    Define formulas to automatically calculate field values
                  </p>
                  <Button size="sm" onClick={addAutoCalculationRule}>
                    <Plus className="w-4 h-4 mr-2" />
                    Add Calculation
                  </Button>
                </div>
                {formData.autoCalculationRules?.map((rule) => (
                  <div key={rule.id} className="p-4 border rounded-lg space-y-3">
                    <div className="flex items-start gap-4">
                      <div className="flex-1 space-y-2">
                        <Label>Target Field</Label>
                        <Select
                          value={rule.targetField}
                          onValueChange={(value) => updateAutoCalculationRule(rule.id, { targetField: value })}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Select field" />
                          </SelectTrigger>
                          <SelectContent>
                            {formData.customFields
                              ?.filter((f) => f.type === "number")
                              .map((field) => (
                                <SelectItem key={field.id} value={field.name}>
                                  {field.name}
                                </SelectItem>
                              ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex-1 space-y-2">
                        <Label>Formula</Label>
                        <Input
                          placeholder="e.g., amount * 0.18"
                          value={rule.formula}
                          onChange={(e) => updateAutoCalculationRule(rule.id, { formula: e.target.value })}
                        />
                        <p className="text-xs text-muted-foreground">Use field names and operators (+, -, *, /)</p>
                      </div>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="mt-8"
                        onClick={() => removeAutoCalculationRule(rule.id)}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                    <div className="space-y-2">
                      <Label>Description</Label>
                      <Input
                        placeholder="e.g., Calculate 18% tax on amount"
                        value={rule.description}
                        onChange={(e) => updateAutoCalculationRule(rule.id, { description: e.target.value })}
                      />
                    </div>
                  </div>
                ))}
              </TabsContent>

              <TabsContent value="defaults" className="space-y-4 mt-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">Set default values for fields based on conditions</p>
                  <Button size="sm" onClick={addDefaultValueRule}>
                    <Plus className="w-4 h-4 mr-2" />
                    Add Default
                  </Button>
                </div>
                {formData.defaultValueRules?.map((rule) => (
                  <div key={rule.id} className="p-4 border rounded-lg space-y-3">
                    <div className="flex items-start gap-4">
                      <div className="flex-1 space-y-2">
                        <Label>Field Name</Label>
                        <Select
                          value={rule.fieldName}
                          onValueChange={(value) => updateDefaultValueRule(rule.id, { fieldName: value })}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Select field" />
                          </SelectTrigger>
                          <SelectContent>
                            {formData.customFields?.map((field) => (
                              <SelectItem key={field.id} value={field.name}>
                                {field.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex-1 space-y-2">
                        <Label>Default Value</Label>
                        <Input
                          placeholder="e.g., Current Year"
                          value={rule.defaultValue}
                          onChange={(e) => updateDefaultValueRule(rule.id, { defaultValue: e.target.value })}
                        />
                      </div>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="mt-8"
                        onClick={() => removeDefaultValueRule(rule.id)}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                    <div className="space-y-2">
                      <Label>Condition (Optional)</Label>
                      <Input
                        placeholder="e.g., when field_name = value"
                        value={rule.condition || ""}
                        onChange={(e) => updateDefaultValueRule(rule.id, { condition: e.target.value })}
                      />
                    </div>
                  </div>
                ))}
              </TabsContent>

              <TabsContent value="approval" className="space-y-4 mt-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">Configure approval workflows for this voucher type</p>
                  <Button size="sm" onClick={addApprovalRule}>
                    <Plus className="w-4 h-4 mr-2" />
                    Add Approval Rule
                  </Button>
                </div>
                {formData.approvalRules?.map((rule) => (
                  <div key={rule.id} className="p-4 border rounded-lg space-y-3">
                    <div className="flex items-start gap-4">
                      <div className="flex-1 space-y-2">
                        <Label>Field Name</Label>
                        <Select
                          value={rule.condition.split(" ")[0] || ""}
                          onValueChange={(value) => {
                            const parts = rule.condition.split(" ")
                            const operator = parts[1] || ">"
                            const condValue = parts[2] || ""
                            updateApprovalRule(rule.id, { condition: `${value} ${operator} ${condValue}` })
                          }}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Select field" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="amount">Voucher Amount</SelectItem>
                            {formData.customFields?.map((field) => (
                              <SelectItem key={field.id} value={field.name}>
                                {field.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="w-[150px] space-y-2">
                        <Label>Condition</Label>
                        <Select
                          value={rule.condition.split(" ")[1] || ">"}
                          onValueChange={(value) => {
                            const parts = rule.condition.split(" ")
                            const field = parts[0] || "amount"
                            const condValue = parts[2] || ""
                            updateApprovalRule(rule.id, { condition: `${field} ${value} ${condValue}` })
                          }}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value=">">Greater Than (&gt;)</SelectItem>
                            <SelectItem value="<">Less Than (&lt;)</SelectItem>
                            <SelectItem value=">=">Greater or Equal (&gt;=)</SelectItem>
                            <SelectItem value="<=">Less or Equal (&lt;=)</SelectItem>
                            <SelectItem value="=">Equal (=)</SelectItem>
                            <SelectItem value="!=">Not Equal (!=)</SelectItem>
                            <SelectItem value="has">Contains (has)</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex-1 space-y-2">
                        <Label>Value</Label>
                        <Input
                          placeholder="e.g., 10000"
                          value={rule.condition.split(" ")[2] || ""}
                          onChange={(e) => {
                            const parts = rule.condition.split(" ")
                            const field = parts[0] || "amount"
                            const operator = parts[1] || ">"
                            updateApprovalRule(rule.id, { condition: `${field} ${operator} ${e.target.value}` })
                          }}
                        />
                      </div>
                      <Button variant="ghost" size="icon" className="mt-8" onClick={() => removeApprovalRule(rule.id)}>
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                    <div className="space-y-2">
                      <Label>Approver Role</Label>
                      <Select
                        value={rule.approverRole}
                        onValueChange={(value) => updateApprovalRule(rule.id, { approverRole: value })}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder="Select role" />
                        </SelectTrigger>
                        <SelectContent>
                          {approverRoles.map((role) => (
                            <SelectItem key={role} value={role}>
                              {role}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                ))}
              </TabsContent>
            </Tabs>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold">Custom Voucher Types</h2>
          <p className="text-muted-foreground mt-1">Create and manage custom voucher types for your business needs</p>
        </div>
        <Button onClick={() => setIsCreating(true)}>
          <Plus className="w-4 h-4 mr-2" />
          Create Voucher Type
        </Button>
      </div>

      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Prefix</TableHead>
                <TableHead>Description</TableHead>
                <TableHead>Account Rules</TableHead>
                <TableHead>Custom Fields</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {voucherTypes.map((voucherType, i) => (
                <TableRow key={voucherType.id || voucherType.code || `vt-${i}`}>
                  <TableCell className="font-medium">{voucherType.name}</TableCell>
                  <TableCell>
                    <Badge variant="outline">{voucherType.prefix}</Badge>
                  </TableCell>
                  <TableCell className="max-w-xs truncate">{voucherType.description}</TableCell>
                  <TableCell>
                    {(voucherType.accountRules || []).length > 0 ? (
                      <div className="text-xs space-y-1">
                        {voucherType.accountRules.map((rule: any, idx: number) => (
                          <div key={idx} className="font-mono flex items-center gap-1">
                            <span className={rule.side === 'debit' ? "text-emerald-700 dark:text-emerald-400 font-semibold" : "text-blue-700 dark:text-blue-400 font-semibold"}>
                              {rule.side.toUpperCase()}:
                            </span>
                            <span>
                              {rule.accountGroups?.join(', ') || 'Any'}
                              {rule.defaultAccount && ` (Default: [${rule.defaultAccount}])`}
                            </span>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <span className="text-xs text-muted-foreground italic">No restrictions</span>
                    )}
                  </TableCell>
                  <TableCell>{voucherType.customFields.length} fields</TableCell>
                  <TableCell>
                    <Badge variant={voucherType.active ? "default" : "secondary"}>
                      {voucherType.active ? "Active" : "Inactive"}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button size="sm" variant="ghost" onClick={() => handleEdit(voucherType)}>
                        <Edit className="w-4 h-4" />
                      </Button>
                      <Button size="sm" variant="ghost" onClick={() => handleDelete(voucherType.id)}>
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
