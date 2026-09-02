"use client"

import type React from "react"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Plus,
  Save,
  X,
  GripVertical,
  FileUp,
  Pen,
  Table2,
  Link,
  DollarSign,
  MapPin,
  Eye,
  EyeOff,
  Download,
  Upload,
  Settings2,
  Zap,
} from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Checkbox } from "@/components/ui/checkbox"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Separator } from "@/components/ui/separator"
import { ScrollArea } from "@/components/ui/scroll-area"

interface VoucherField {
  id: string
  name: string
  type:
    | "text"
    | "number"
    | "date"
    | "dropdown"
    | "multiselect"
    | "checkbox"
    | "textarea"
    | "file"
    | "signature"
    | "computed"
    | "table"
    | "reference"
    | "currency"
    | "location"
  required: boolean
  options?: string[]
  formula?: string
  width?: "full" | "half" | "third"
  section?: string
  conditionalDisplay?: {
    field: string
    operator: "equals" | "not_equals" | "greater_than" | "less_than" | "contains"
    value: string
  }
  helpText?: string
  tableColumns?: { name: string; type: string }[]
  referenceEntity?: string
}

interface FieldSection {
  id: string
  name: string
  collapsible: boolean
  fields: string[]
}

interface PermissionRule {
  role: string
  create: boolean
  view: boolean
  edit: boolean
  delete: boolean
  approve: boolean
  hiddenFields: string[]
}

export default function VoucherBuilder() {
  const [voucherName, setVoucherName] = useState("")
  const [voucherPrefix, setVoucherPrefix] = useState("")
  const [description, setDescription] = useState("")

  const [fields, setFields] = useState<VoucherField[]>([])
  const [sections, setSections] = useState<FieldSection[]>([
    { id: "default", name: "Default Section", collapsible: false, fields: [] },
  ])
  const [permissions, setPermissions] = useState<PermissionRule[]>([
    {
      role: "Admin",
      create: true,
      view: true,
      edit: true,
      delete: true,
      approve: true,
      hiddenFields: [],
    },
  ])

  const [draggedField, setDraggedField] = useState<VoucherField | null>(null)
  const [previewMode, setPreviewMode] = useState(false)

  const fieldTypes = [
    { type: "text", label: "Text", icon: "T" },
    { type: "number", label: "Number", icon: "#" },
    { type: "date", label: "Date", icon: "📅" },
    { type: "dropdown", label: "Dropdown", icon: "▼" },
    { type: "multiselect", label: "Multi-Select", icon: "☑" },
    { type: "checkbox", label: "Checkbox", icon: "✓" },
    { type: "textarea", label: "Text Area", icon: "¶" },
    { type: "file", label: "File Upload", icon: <FileUp className="w-4 h-4" /> },
    { type: "signature", label: "Signature", icon: <Pen className="w-4 h-4" /> },
    { type: "computed", label: "Computed", icon: "∑" },
    { type: "table", label: "Table/Line Items", icon: <Table2 className="w-4 h-4" /> },
    { type: "reference", label: "Reference", icon: <Link className="w-4 h-4" /> },
    { type: "currency", label: "Currency", icon: <DollarSign className="w-4 h-4" /> },
    { type: "location", label: "Geo-location", icon: <MapPin className="w-4 h-4" /> },
  ]

  const roles = ["Admin", "Accountant", "Manager", "Viewer", "Clerk"]

  const addField = (type: string) => {
    const newField: VoucherField = {
      id: Date.now().toString(),
      name: `New ${type} Field`,
      type: type as any,
      required: false,
      width: "full",
      section: "default",
    }

    if (type === "dropdown" || type === "multiselect") {
      newField.options = []
    }

    if (type === "computed") {
      newField.formula = ""
    }

    if (type === "table") {
      newField.tableColumns = []
    }

    if (type === "reference") {
      newField.referenceEntity = ""
    }

    setFields([...fields, newField])
    const defaultSection = sections.find((s) => s.id === "default")
    if (defaultSection) {
      setSections(sections.map((s) => (s.id === "default" ? { ...s, fields: [...s.fields, newField.id] } : s)))
    }
  }

  const updateField = (id: string, updates: Partial<VoucherField>) => {
    setFields(fields.map((field) => (field.id === id ? { ...field, ...updates } : field)))
  }

  const removeField = (id: string) => {
    setFields(fields.filter((field) => field.id !== id))
    setSections(sections.map((s) => ({ ...s, fields: s.fields.filter((fId) => fId !== id) })))
  }

  const addSection = () => {
    const newSection: FieldSection = {
      id: Date.now().toString(),
      name: "New Section",
      collapsible: true,
      fields: [],
    }
    setSections([...sections, newSection])
  }

  const updateSection = (id: string, updates: Partial<FieldSection>) => {
    setSections(sections.map((section) => (section.id === id ? { ...section, ...updates } : section)))
  }

  const addPermissionRule = () => {
    const newRule: PermissionRule = {
      role: "Viewer",
      create: false,
      view: true,
      edit: false,
      delete: false,
      approve: false,
      hiddenFields: [],
    }
    setPermissions([...permissions, newRule])
  }

  const updatePermission = (index: number, updates: Partial<PermissionRule>) => {
    setPermissions(permissions.map((perm, i) => (i === index ? { ...perm, ...updates } : perm)))
  }

  const exportTemplate = () => {
    const template = {
      name: voucherName,
      prefix: voucherPrefix,
      description,
      fields,
      sections,
      permissions,
    }
    const blob = new Blob([JSON.stringify(template, null, 2)], { type: "application/json" })
    const url = URL.createObjectURL(blob)
    const a = document.createElement("a")
    a.href = url
    a.download = `${voucherName.replace(/\s+/g, "_")}_template.json`
    a.click()
  }

  const importTemplate = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (file) {
      const reader = new FileReader()
      reader.onload = (e) => {
        try {
          const template = JSON.parse(e.target?.result as string)
          setVoucherName(template.name || "")
          setVoucherPrefix(template.prefix || "")
          setDescription(template.description || "")
          setFields(template.fields || [])
          setSections(template.sections || [])
          setPermissions(template.permissions || [])
        } catch (error) {
          console.error("Failed to import template:", error)
        }
      }
      reader.readAsText(file)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2.5">
            <h2 className="text-3xl font-bold">Voucher Builder</h2>
            <span className="text-xs uppercase font-semibold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
              Experimental
            </span>
          </div>
          <p className="text-muted-foreground mt-1">Design custom voucher types with drag-and-drop</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={exportTemplate}>
            <Download className="w-4 h-4 mr-2" />
            Export
          </Button>
          <Button variant="outline" size="sm" onClick={() => document.getElementById("import-file")?.click()}>
            <Upload className="w-4 h-4 mr-2" />
            Import
            <input id="import-file" type="file" accept=".json" className="hidden" onChange={importTemplate} />
          </Button>
          <Button variant="outline" onClick={() => setPreviewMode(!previewMode)}>
            {previewMode ? <EyeOff className="w-4 h-4 mr-2" /> : <Eye className="w-4 h-4 mr-2" />}
            {previewMode ? "Edit Mode" : "Preview"}
          </Button>
          <Button>
            <Save className="w-4 h-4 mr-2" />
            Save Template
          </Button>
        </div>
      </div>

      {/* Experimental Notice */}
      <div className="flex items-start gap-3 p-3.5 bg-amber-500/10 border border-amber-500/30 rounded-lg text-sm text-amber-900 dark:text-amber-300">
        <div className="font-semibold px-2 py-0.5 text-xs rounded bg-amber-500/20 uppercase tracking-wide shrink-0 mt-0.5">
          Notice
        </div>
        <div>
          <strong>Experimental Feature:</strong> The dynamic Voucher Builder is currently under active development and evaluation. Kamal Express-specific custom fields (such as PNR, Passenger Name, Ticket Number, Sector) will be configured and validated here before declaring this feature production ready.
        </div>
      </div>

      {/* Basic Info */}
      <Card>
        <CardHeader>
          <CardTitle>Basic Information</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Voucher Type Name</Label>
              <Input
                placeholder="e.g., School Fees Voucher"
                value={voucherName}
                onChange={(e) => setVoucherName(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>Prefix</Label>
              <Input placeholder="e.g., SF" value={voucherPrefix} onChange={(e) => setVoucherPrefix(e.target.value)} />
            </div>
          </div>
          <div className="space-y-2">
            <Label>Description</Label>
            <Textarea
              placeholder="Describe the purpose of this voucher type"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>
        </CardContent>
      </Card>

      {/* Main Builder */}
      <div className="grid grid-cols-3 gap-6">
        {/* Field Palette */}
        <Card className="col-span-1">
          <CardHeader>
            <CardTitle className="text-lg">Field Palette</CardTitle>
            <CardDescription>Drag fields to the canvas</CardDescription>
          </CardHeader>
          <CardContent>
            <ScrollArea className="h-[600px]">
              <div className="space-y-2">
                {fieldTypes.map((fieldType) => (
                  <div
                    key={fieldType.type}
                    className="flex items-center gap-3 p-3 border rounded-lg cursor-move hover:bg-accent transition-colors"
                    draggable
                    onDragStart={() => addField(fieldType.type)}
                  >
                    <div className="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-sm font-semibold">
                      {typeof fieldType.icon === "string" ? fieldType.icon : fieldType.icon}
                    </div>
                    <span className="text-sm font-medium">{fieldType.label}</span>
                  </div>
                ))}
              </div>
            </ScrollArea>
          </CardContent>
        </Card>

        {/* Canvas & Preview */}
        <div className="col-span-2 space-y-4">
          {previewMode ? (
            <Card>
              <CardHeader>
                <CardTitle>Preview: {voucherName || "Untitled Voucher"}</CardTitle>
                <CardDescription>{description}</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-6">
                  {sections.map((section) => (
                    <div key={section.id} className="space-y-4">
                      {section.name !== "Default Section" && (
                        <h3 className="text-lg font-semibold border-b pb-2">{section.name}</h3>
                      )}
                      <div className="grid grid-cols-1 gap-4">
                        {section.fields.map((fieldId) => {
                          const field = fields.find((f) => f.id === fieldId)
                          if (!field) return null

                          const getGridClass = () => {
                            switch (field.width) {
                              case "third":
                                return "col-span-1"
                              case "half":
                                return "md:col-span-1"
                              default:
                                return "col-span-full"
                            }
                          }

                          return (
                            <div key={field.id} className={getGridClass()}>
                              <Label>
                                {field.name}
                                {field.required && <span className="text-destructive ml-1">*</span>}
                              </Label>
                              {field.helpText && <p className="text-xs text-muted-foreground mb-1">{field.helpText}</p>}
                              {field.type === "textarea" ? (
                                <Textarea placeholder={`Enter ${field.name}`} />
                              ) : field.type === "dropdown" ? (
                                <Select>
                                  <SelectTrigger>
                                    <SelectValue placeholder={`Select ${field.name}`} />
                                  </SelectTrigger>
                                  <SelectContent>
                                    {field.options?.map((opt) => (
                                      <SelectItem key={opt} value={opt}>
                                        {opt}
                                      </SelectItem>
                                    ))}
                                  </SelectContent>
                                </Select>
                              ) : field.type === "checkbox" ? (
                                <div className="flex items-center gap-2">
                                  <Checkbox />
                                  <Label className="text-sm font-normal">{field.name}</Label>
                                </div>
                              ) : field.type === "file" ? (
                                <Input type="file" />
                              ) : field.type === "signature" ? (
                                <div className="border rounded-md h-24 flex items-center justify-center text-muted-foreground text-sm">
                                  Signature Pad
                                </div>
                              ) : field.type === "table" ? (
                                <div className="border rounded-md p-2">
                                  <div className="text-sm text-muted-foreground">Table: Line Items</div>
                                </div>
                              ) : (
                                <Input
                                  type={field.type === "number" ? "number" : field.type === "date" ? "date" : "text"}
                                  placeholder={`Enter ${field.name}`}
                                />
                              )}
                            </div>
                          )
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          ) : (
            <Tabs defaultValue="layout">
              <TabsList className="grid w-full grid-cols-5">
                <TabsTrigger value="layout">Layout & Fields</TabsTrigger>
                <TabsTrigger value="numbering">Numbering</TabsTrigger>
                <TabsTrigger value="automation">Automation</TabsTrigger>
                <TabsTrigger value="permissions">Permissions</TabsTrigger>
                <TabsTrigger value="print">Print</TabsTrigger>
              </TabsList>

              <TabsContent value="layout" className="space-y-4">
                <Card>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <CardTitle className="text-lg">Form Layout</CardTitle>
                      <Button size="sm" onClick={addSection}>
                        <Plus className="w-4 h-4 mr-2" />
                        Add Section
                      </Button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <ScrollArea className="h-[500px]">
                      <div className="space-y-6">
                        {sections.map((section) => (
                          <div key={section.id} className="border rounded-lg p-4 space-y-3">
                            <div className="flex items-center gap-2">
                              <Input
                                className="flex-1"
                                value={section.name}
                                onChange={(e) => updateSection(section.id, { name: e.target.value })}
                                placeholder="Section Name"
                              />
                              <Checkbox
                                checked={section.collapsible}
                                onCheckedChange={(checked) =>
                                  updateSection(section.id, { collapsible: checked as boolean })
                                }
                              />
                              <Label className="text-sm">Collapsible</Label>
                            </div>
                            <Separator />
                            <div className="space-y-2">
                              {section.fields.map((fieldId) => {
                                const field = fields.find((f) => f.id === fieldId)
                                if (!field) return null

                                return (
                                  <div key={field.id} className="flex items-center gap-3 p-3 border rounded-lg bg-card">
                                    <GripVertical className="w-4 h-4 text-muted-foreground cursor-move" />
                                    <div className="flex-1">
                                      <div className="flex items-center gap-2">
                                        <Input
                                          value={field.name}
                                          onChange={(e) => updateField(field.id, { name: e.target.value })}
                                          className="flex-1"
                                        />
                                        <Badge variant="outline">{field.type}</Badge>
                                      </div>
                                      <div className="grid grid-cols-3 gap-2 mt-2">
                                        <Select
                                          value={field.width}
                                          onValueChange={(value: any) => updateField(field.id, { width: value })}
                                        >
                                          <SelectTrigger className="h-8">
                                            <SelectValue />
                                          </SelectTrigger>
                                          <SelectContent>
                                            <SelectItem value="full">Full Width</SelectItem>
                                            <SelectItem value="half">Half Width</SelectItem>
                                            <SelectItem value="third">Third Width</SelectItem>
                                          </SelectContent>
                                        </Select>
                                        <div className="flex items-center gap-1">
                                          <Checkbox
                                            checked={field.required}
                                            onCheckedChange={(checked) =>
                                              updateField(field.id, { required: checked as boolean })
                                            }
                                          />
                                          <Label className="text-xs">Required</Label>
                                        </div>
                                      </div>
                                      {(field.type === "dropdown" || field.type === "multiselect") && (
                                        <Input
                                          className="mt-2"
                                          placeholder="Options (comma-separated)"
                                          value={field.options?.join(", ") || ""}
                                          onChange={(e) =>
                                            updateField(field.id, {
                                              options: e.target.value
                                                .split(",")
                                                .map((opt) => opt.trim())
                                                .filter(Boolean),
                                            })
                                          }
                                        />
                                      )}
                                      {field.type === "computed" && (
                                        <Input
                                          className="mt-2"
                                          placeholder="Formula (e.g., amount * 0.18)"
                                          value={field.formula || ""}
                                          onChange={(e) => updateField(field.id, { formula: e.target.value })}
                                        />
                                      )}
                                      {field.type === "reference" && (
                                        <Input
                                          className="mt-2"
                                          placeholder="Reference Entity (e.g., Customer)"
                                          value={field.referenceEntity || ""}
                                          onChange={(e) => updateField(field.id, { referenceEntity: e.target.value })}
                                        />
                                      )}
                                      <Input
                                        className="mt-2"
                                        placeholder="Help text (tooltip)"
                                        value={field.helpText || ""}
                                        onChange={(e) => updateField(field.id, { helpText: e.target.value })}
                                      />
                                    </div>
                                    <Button variant="ghost" size="icon" onClick={() => removeField(field.id)}>
                                      <X className="w-4 h-4" />
                                    </Button>
                                  </div>
                                )
                              })}
                            </div>
                          </div>
                        ))}
                      </div>
                    </ScrollArea>
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="numbering">
                <Card>
                  <CardHeader>
                    <CardTitle>Smart Numbering Scheme</CardTitle>
                    <CardDescription>Advanced numbering options with branch and department support</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-2">
                        <Label>Starting Number</Label>
                        <Input type="number" defaultValue="1" />
                      </div>
                      <div className="space-y-2">
                        <Label>Number Padding</Label>
                        <Input type="number" defaultValue="4" min="0" max="10" />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-2">
                        <Label>Branch Code (Optional)</Label>
                        <Input placeholder="e.g., HQ, BR01" />
                      </div>
                      <div className="space-y-2">
                        <Label>Department Code (Optional)</Label>
                        <Input placeholder="e.g., ACC, FIN" />
                      </div>
                    </div>
                    <div className="space-y-2">
                      <Label>Reset Period</Label>
                      <Select defaultValue="yearly">
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="never">Never</SelectItem>
                          <SelectItem value="yearly">Financial Year</SelectItem>
                          <SelectItem value="monthly">Monthly</SelectItem>
                          <SelectItem value="quarterly">Quarterly</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="flex items-center gap-2">
                      <Checkbox defaultChecked />
                      <Label>Draft vouchers use temporary numbering</Label>
                    </div>
                    <div className="p-3 bg-muted rounded-md">
                      <Label className="text-sm font-medium">Preview:</Label>
                      <p className="text-sm text-muted-foreground mt-1">{voucherPrefix || "SF"}-HQ-2024-0001</p>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="automation">
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Zap className="w-5 h-5" />
                      Automation & Triggers
                    </CardTitle>
                    <CardDescription>Configure auto-actions and workflows</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-6">
                    <div className="space-y-3">
                      <h4 className="font-semibold">Post-Save Actions</h4>
                      <div className="space-y-2">
                        <div className="flex items-center gap-2">
                          <Checkbox />
                          <Label className="font-normal">Send email notification</Label>
                        </div>
                        <div className="flex items-center gap-2">
                          <Checkbox />
                          <Label className="font-normal">Send SMS notification</Label>
                        </div>
                        <div className="flex items-center gap-2">
                          <Checkbox />
                          <Label className="font-normal">Auto-generate ledger postings</Label>
                        </div>
                        <div className="flex items-center gap-2">
                          <Checkbox />
                          <Label className="font-normal">Print automatically</Label>
                        </div>
                        <div className="flex items-center gap-2">
                          <Checkbox />
                          <Label className="font-normal">Trigger webhook</Label>
                        </div>
                      </div>
                    </div>
                    <Separator />
                    <div className="space-y-3">
                      <h4 className="font-semibold">Workflow Rules</h4>
                      <div className="p-3 border rounded-lg space-y-2">
                        <div className="flex items-center gap-2">
                          <Label className="text-sm">IF</Label>
                          <Select>
                            <SelectTrigger className="flex-1">
                              <SelectValue placeholder="Select field" />
                            </SelectTrigger>
                            <SelectContent>
                              {fields.map((f) => (
                                <SelectItem key={f.id} value={f.name}>
                                  {f.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          <Select>
                            <SelectTrigger className="w-32">
                              <SelectValue placeholder="Operator" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value=">">{">"}</SelectItem>
                              <SelectItem value="<">{"<"}</SelectItem>
                              <SelectItem value="=">{"="}</SelectItem>
                              <SelectItem value="contains">contains</SelectItem>
                            </SelectContent>
                          </Select>
                          <Input className="flex-1" placeholder="Value" />
                        </div>
                        <div className="flex items-center gap-2">
                          <Label className="text-sm">THEN</Label>
                          <Select>
                            <SelectTrigger className="flex-1">
                              <SelectValue placeholder="Select action" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="approval">Send for approval</SelectItem>
                              <SelectItem value="notify">Send notification</SelectItem>
                              <SelectItem value="set_field">Set field value</SelectItem>
                              <SelectItem value="task">Create task</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                      </div>
                      <Button size="sm" variant="outline">
                        <Plus className="w-4 h-4 mr-2" />
                        Add Rule
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="permissions">
                <Card>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div>
                        <CardTitle>Role-Based Permissions</CardTitle>
                        <CardDescription>Configure access control per role</CardDescription>
                      </div>
                      <Button size="sm" onClick={addPermissionRule}>
                        <Plus className="w-4 h-4 mr-2" />
                        Add Role
                      </Button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <ScrollArea className="h-[500px]">
                      <div className="space-y-4">
                        {permissions.map((perm, index) => (
                          <div key={index} className="p-4 border rounded-lg space-y-4">
                            <div className="flex items-center justify-between">
                              <Select
                                value={perm.role}
                                onValueChange={(value) => updatePermission(index, { role: value })}
                              >
                                <SelectTrigger className="w-[200px]">
                                  <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                  {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                      {role}
                                    </SelectItem>
                                  ))}
                                </SelectContent>
                              </Select>
                              <Badge>{perm.role}</Badge>
                            </div>
                            <div className="grid grid-cols-5 gap-4">
                              <div className="flex items-center gap-2">
                                <Checkbox
                                  checked={perm.create}
                                  onCheckedChange={(checked) => updatePermission(index, { create: checked as boolean })}
                                />
                                <Label className="text-sm">Create</Label>
                              </div>
                              <div className="flex items-center gap-2">
                                <Checkbox
                                  checked={perm.view}
                                  onCheckedChange={(checked) => updatePermission(index, { view: checked as boolean })}
                                />
                                <Label className="text-sm">View</Label>
                              </div>
                              <div className="flex items-center gap-2">
                                <Checkbox
                                  checked={perm.edit}
                                  onCheckedChange={(checked) => updatePermission(index, { edit: checked as boolean })}
                                />
                                <Label className="text-sm">Edit</Label>
                              </div>
                              <div className="flex items-center gap-2">
                                <Checkbox
                                  checked={perm.delete}
                                  onCheckedChange={(checked) => updatePermission(index, { delete: checked as boolean })}
                                />
                                <Label className="text-sm">Delete</Label>
                              </div>
                              <div className="flex items-center gap-2">
                                <Checkbox
                                  checked={perm.approve}
                                  onCheckedChange={(checked) =>
                                    updatePermission(index, { approve: checked as boolean })
                                  }
                                />
                                <Label className="text-sm">Approve</Label>
                              </div>
                            </div>
                            <div className="space-y-2">
                              <Label className="text-sm">Hidden Fields</Label>
                              <div className="grid grid-cols-3 gap-2">
                                {fields.map((field) => (
                                  <div key={field.id} className="flex items-center gap-2">
                                    <Checkbox
                                      checked={perm.hiddenFields.includes(field.id)}
                                      onCheckedChange={(checked) => {
                                        const newHiddenFields = checked
                                          ? [...perm.hiddenFields, field.id]
                                          : perm.hiddenFields.filter((id) => id !== field.id)
                                        updatePermission(index, { hiddenFields: newHiddenFields })
                                      }}
                                    />
                                    <Label className="text-xs">{field.name}</Label>
                                  </div>
                                ))}
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </ScrollArea>
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="print">
                <Card>
                  <CardHeader>
                    <CardTitle>Print Template Designer</CardTitle>
                    <CardDescription>Customize print layout and appearance</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="space-y-2">
                      <Label>Template Style</Label>
                      <Select defaultValue="classic">
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="classic">Classic</SelectItem>
                          <SelectItem value="modern">Modern</SelectItem>
                          <SelectItem value="minimal">Minimal</SelectItem>
                          <SelectItem value="custom">Custom</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label>Logo Position</Label>
                      <Select defaultValue="left">
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="left">Left</SelectItem>
                          <SelectItem value="center">Center</SelectItem>
                          <SelectItem value="right">Right</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="flex items-center gap-2">
                        <Checkbox defaultChecked />
                        <Label>Show Company Logo</Label>
                      </div>
                      <div className="flex items-center gap-2">
                        <Checkbox defaultChecked />
                        <Label>Show Header</Label>
                      </div>
                      <div className="flex items-center gap-2">
                        <Checkbox defaultChecked />
                        <Label>Show Footer</Label>
                      </div>
                      <div className="flex items-center gap-2">
                        <Checkbox defaultChecked />
                        <Label>Show QR Code</Label>
                      </div>
                    </div>
                    <div className="space-y-2">
                      <Label>Footer Note</Label>
                      <Textarea placeholder="e.g., This is a computer generated document" />
                    </div>
                    <Button variant="outline" className="w-full bg-transparent">
                      <Settings2 className="w-4 h-4 mr-2" />
                      Advanced Template Settings
                    </Button>
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          )}
        </div>
      </div>
    </div>
  )
}
