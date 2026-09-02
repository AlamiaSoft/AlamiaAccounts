"use client"

import type React from "react"

import { useState } from "react"
import { Building2, Edit, Trash2, Plus } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import type { Company } from "./company-switcher"

interface CompanyManagementProps {
  companies: Company[]
  onAddCompany: (company: Omit<Company, "id">) => void
  onEditCompany: (id: string, company: Omit<Company, "id">) => void
  onDeleteCompany: (id: string) => void
}

export default function CompanyManagement({
  companies,
  onAddCompany,
  onEditCompany,
  onDeleteCompany,
}: CompanyManagementProps) {
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingCompany, setEditingCompany] = useState<Company | null>(null)
  const [formData, setFormData] = useState({ name: "", industry: "", code: "", currency: "PKR" })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editingCompany) {
      onEditCompany(editingCompany.id, formData)
    } else {
      onAddCompany(formData)
    }
    setIsDialogOpen(false)
    setEditingCompany(null)
    setFormData({ name: "", industry: "", code: "", currency: "PKR" })
  }

  const handleEdit = (company: Company) => {
    setEditingCompany(company)
    setFormData({
      name: company.name,
      industry: company.industry,
      code: company.id, // In our mapping id is code
      currency: company.currency || "PKR"
    })
    setIsDialogOpen(true)
  }

  const handleAdd = () => {
    setEditingCompany(null)
    setFormData({ name: "", industry: "", code: "", currency: "PKR" })
    setIsDialogOpen(true)
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold">Company Management</h2>
          <p className="text-muted-foreground mt-1">Manage your client companies</p>
        </div>
        <Button onClick={handleAdd}>
          <Plus className="w-4 h-4 mr-2" />
          Add Company
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Companies</CardTitle>
          <CardDescription>View and manage all client companies in your portfolio</CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Company Name</TableHead>
                <TableHead>Industry</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {companies.map((company, i) => (
                <TableRow key={company.id || company.code || `company-${i}`}>
                  <TableCell className="font-medium">
                    <div className="flex items-center gap-2">
                      <Building2 className="w-4 h-4 text-muted-foreground" />
                      {company.name}
                    </div>
                  </TableCell>
                  <TableCell>{company.industry}</TableCell>
                  <TableCell className="text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Button variant="ghost" size="sm" onClick={() => handleEdit(company)}>
                        <Edit className="w-4 h-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          if (company.id === "MAIN" || company.code === "MAIN") {
                            alert("The Main Company cannot be deleted.")
                            return
                          }
                          if (confirm(`Are you sure you want to delete company "${company.name}"? This action cannot be undone.`)) {
                            onDeleteCompany(company.id)
                          }
                        }}
                        className="text-destructive hover:text-destructive"
                        title={company.id === "MAIN" ? "Main company cannot be deleted" : "Delete company"}
                      >
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

      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent>
          <form onSubmit={handleSubmit}>
            <DialogHeader>
              <DialogTitle>{editingCompany ? "Edit Company" : "Add New Company"}</DialogTitle>
              <DialogDescription>
                {editingCompany ? "Update the company information below." : "Enter the details of the new company."}
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label htmlFor="name">Company Name</Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="Enter company name"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="code">Company Code</Label>
                <Input
                  id="code"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                  placeholder="e.g., COMP1, ALAMIA"
                  required
                  disabled={!!editingCompany}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="industry">Industry</Label>
                <Input
                  id="industry"
                  value={formData.industry}
                  onChange={(e) => setFormData({ ...formData, industry: e.target.value })}
                  placeholder="e.g., Manufacturing, Retail, Services"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="currency">Default Currency</Label>
                <Input
                  id="currency"
                  value={formData.currency}
                  onChange={(e) => setFormData({ ...formData, currency: e.target.value })}
                  placeholder="e.g., PKR, USD, INR"
                  required
                />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setIsDialogOpen(false)}>
                Cancel
              </Button>
              <Button type="submit">{editingCompany ? "Update" : "Add"} Company</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
