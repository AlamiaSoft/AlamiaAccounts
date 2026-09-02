"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Label } from "@/components/ui/label"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Printer, Save } from "lucide-react"

export interface PrintSettings {
  companyName: string
  companyAddress?: string
  companyPhone?: string
  companyEmail?: string
  logo?: string
  footerNote?: string
  showHeader: boolean
  showFooter: boolean
}

interface PrintTemplateSettingsProps {
  onSave: (settings: PrintSettings) => void
  initialSettings?: PrintSettings
}

export default function PrintTemplateSettings({ onSave, initialSettings }: PrintTemplateSettingsProps) {
  const [settings, setSettings] = useState<PrintSettings>(
    initialSettings || {
      companyName: "Acme Corporation",
      companyAddress: "123 Business Park, Islamabad, Punjab 400001",
      companyPhone: "+92 22 1234 5678",
      companyEmail: "accounts@acme.com",
      footerNote: "This is a computer generated document and does not require signature.",
      showHeader: true,
      showFooter: true,
    },
  )

  const handleSave = () => {
    onSave(settings)
    alert("Print template settings saved successfully!")
  }

  return (
    <div className="w-full max-w-6xl mx-auto p-4 md:p-6 lg:p-8">
      <div className="mb-8">
        <div className="flex items-center gap-3 mb-2">
          <Printer className="w-8 h-8 text-primary" />
          <h1 className="text-3xl font-bold text-foreground">Print Template Settings</h1>
        </div>
        <p className="text-muted-foreground">Configure print templates for vouchers and financial reports</p>
      </div>

      <Tabs defaultValue="vouchers" className="space-y-6">
        <TabsList>
          <TabsTrigger value="vouchers">Vouchers</TabsTrigger>
          <TabsTrigger value="reports">Financial Reports</TabsTrigger>
        </TabsList>

        <TabsContent value="vouchers" className="space-y-6">
          {/* Header Settings */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>Header Configuration</CardTitle>
                  <CardDescription>Customize the header section of printed vouchers</CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <Label htmlFor="show-header">Show Header</Label>
                  <Switch
                    id="show-header"
                    checked={settings.showHeader}
                    onCheckedChange={(checked) => setSettings({ ...settings, showHeader: checked })}
                  />
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="company-name">Company Name</Label>
                  <Input
                    id="company-name"
                    value={settings.companyName}
                    onChange={(e) => setSettings({ ...settings, companyName: e.target.value })}
                    placeholder="Acme Corporation"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="company-email">Email</Label>
                  <Input
                    id="company-email"
                    type="email"
                    value={settings.companyEmail || ""}
                    onChange={(e) => setSettings({ ...settings, companyEmail: e.target.value })}
                    placeholder="accounts@company.com"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="company-address">Address</Label>
                <Input
                  id="company-address"
                  value={settings.companyAddress || ""}
                  onChange={(e) => setSettings({ ...settings, companyAddress: e.target.value })}
                  placeholder="123 Business Park, City, State - PIN"
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="company-phone">Phone</Label>
                  <Input
                    id="company-phone"
                    value={settings.companyPhone || ""}
                    onChange={(e) => setSettings({ ...settings, companyPhone: e.target.value })}
                    placeholder="+92 12345 67890"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="logo-url">Logo URL</Label>
                  <Input
                    id="logo-url"
                    value={settings.logo || ""}
                    onChange={(e) => setSettings({ ...settings, logo: e.target.value })}
                    placeholder="https://example.com/logo.png"
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Footer Settings */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>Footer Configuration</CardTitle>
                  <CardDescription>Add footer notes and disclaimers to printed vouchers</CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <Label htmlFor="show-footer">Show Footer</Label>
                  <Switch
                    id="show-footer"
                    checked={settings.showFooter}
                    onCheckedChange={(checked) => setSettings({ ...settings, showFooter: checked })}
                  />
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="footer-note">Footer Note</Label>
                <textarea
                  id="footer-note"
                  value={settings.footerNote || ""}
                  onChange={(e) => setSettings({ ...settings, footerNote: e.target.value })}
                  placeholder="Add terms, conditions, or disclaimers..."
                  className="w-full px-3 py-2 bg-input border border-input rounded-md text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                  rows={3}
                />
              </div>
            </CardContent>
          </Card>

          {/* Preview */}
          <Card>
            <CardHeader>
              <CardTitle>Preview</CardTitle>
              <CardDescription>Preview how your vouchers will look when printed</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="bg-white text-black p-8 rounded border-2 border-dashed border-muted-foreground/50">
                {settings.showHeader && (
                  <div className="border-b-2 border-black pb-4 mb-4">
                    <h2 className="text-xl font-bold">{settings.companyName}</h2>
                    {settings.companyAddress && <p className="text-sm mt-1">{settings.companyAddress}</p>}
                    <div className="flex gap-4 text-sm mt-1">
                      {settings.companyPhone && <span>Tel: {settings.companyPhone}</span>}
                      {settings.companyEmail && <span>Email: {settings.companyEmail}</span>}
                    </div>
                  </div>
                )}
                <div className="text-center py-8 text-gray-500">Voucher content will appear here</div>
                {settings.showFooter && settings.footerNote && (
                  <div className="border-t border-black pt-4 mt-4 text-center text-sm text-gray-600">
                    {settings.footerNote}
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="reports" className="space-y-6">
          <Card>
            <CardContent className="py-12">
              <div className="text-center text-muted-foreground">
                <p>Financial report print templates coming soon</p>
                <p className="text-sm mt-2">Configure print settings for Balance Sheet, P&L, and other reports</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <div className="flex justify-end mt-6">
        <Button onClick={handleSave} size="lg">
          <Save className="w-4 h-4 mr-2" />
          Save Settings
        </Button>
      </div>
    </div>
  )
}
