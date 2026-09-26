"use client"

import { useState, useEffect, useCallback } from "react"
import apiClient from "@/lib/api-client"
import { 
  Activity, 
  Download, 
  RefreshCw, 
  Trash2, 
  CheckCircle2, 
  AlertTriangle, 
  BookOpen, 
  Cpu, 
  Zap, 
  Clock, 
  Search, 
  Filter, 
  Eye, 
  Check, 
  X, 
  Plus, 
  Sparkles, 
  Terminal,
  FileJson,
  ArrowRight,
  ShieldCheck,
  ShieldAlert,
  HelpCircle,
  ExternalLink,
  Layers,
  Maximize2,
  Minimize2
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { useToast } from "@/components/ui/use-toast"

interface DiagnosticTrace {
  id: number
  session_id: string | null
  company_code: string | null
  user_name: string | null
  prompt: string
  classifier_mode: string
  classifier_intent: string | null
  classifier_confidence: number | null
  classifier_output: any
  context_before: any
  context_after: any
  safety_evaluations: any
  dispatched_action: string | null
  execution_result: any
  final_response: any
  duration_ms: number | null
  status: "unreviewed" | "verified" | "needs_fix" | "promoted_to_kb"
  anomaly_flag: string | null
  entry_point: string | null
  developer_notes: string | null
  created_at: string
}

interface TelemetryStats {
  total_traces: number
  verified_count: number
  needs_fix_count: number
  promoted_to_kb_count: number
  unreviewed_count: number
  avg_duration_ms: number
  mode_breakdown: Record<string, number>
  active_knowledge_entries: number
  anomaly_count: number
}

interface KnowledgeEntry {
  id: number
  company_code: string | null
  topic: string
  trigger_keywords: string[]
  domain: string
  title: string
  summary: string
  steps: string[] | null
  note: string | null
  actions: any[] | null
  source_diagnostic_id: number | null
  is_active: boolean
  created_at: string
}

export default function SystemDiagnostics() {
  const { toast } = useToast()
  const [activeTab, setActiveTab] = useState<"traces" | "knowledge">("traces")
  const [traces, setTraces] = useState<DiagnosticTrace[]>([])
  const [stats, setStats] = useState<TelemetryStats | null>(null)
  const [knowledgeList, setKnowledgeList] = useState<KnowledgeEntry[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [selectedTrace, setSelectedTrace] = useState<DiagnosticTrace | null>(null)
  const [isInspectOpen, setIsInspectOpen] = useState(false)
  const [isInspectMaximized, setIsInspectMaximized] = useState(false)
  const [isPromoteModalOpen, setIsPromoteModalOpen] = useState(false)
  const [isAddKnowledgeModalOpen, setIsAddKnowledgeModalOpen] = useState(false)
  const [isPruneModalOpen, setIsPruneModalOpen] = useState(false)
  const [isLoadingKnowledge, setIsLoadingKnowledge] = useState(false)

  // Filters
  const [searchQuery, setSearchQuery] = useState("")
  const [filterMode, setFilterMode] = useState<string>("all")
  const [filterStatus, setFilterStatus] = useState<string>("all")
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)

  // Developer Feedback state
  const [devNotes, setDevNotes] = useState("")
  const [devStatus, setDevStatus] = useState<"unreviewed" | "verified" | "needs_fix" | "promoted_to_kb">("unreviewed")

  // Promote / Knowledge form state
  const [kbTopic, setKbTopic] = useState("")
  const [kbDomain, setKbDomain] = useState("general")
  const [kbTriggers, setKbTriggers] = useState("")
  const [kbTitle, setKbTitle] = useState("")
  const [kbSummary, setKbSummary] = useState("")
  const [kbSteps, setKbSteps] = useState("")
  const [kbNote, setKbNote] = useState("")

  const fetchDiagnostics = useCallback(async () => {
    setIsLoading(true)
    try {
      const params: any = {
        page,
        per_page: 20,
      }
      if (searchQuery.trim()) params.search = searchQuery.trim()
      if (filterMode !== "all") params.classifier_mode = filterMode
      if (filterStatus !== "all" && filterStatus !== "anomaly") params.status = filterStatus
      // Special handling: anomaly filter queries by anomaly_flag presence, not status
      if (filterStatus === "anomaly") {
        params.anomaly_flag = "any"
      }

      let res
      try {
        res = await apiClient.get("/copilot/diagnostics", { params })
      } catch (e) {
        res = await apiClient.get("/copilot/diagnostics-public", { params })
      }
      if (res.data?.success) {
        setTraces(res.data.data || [])
        setStats(res.data.stats || null)
        if (res.data.pagination) {
          setTotalPages(res.data.pagination.last_page || 1)
        }
      }
    } catch (err: any) {
      console.error("Failed to load diagnostics:", err)
      toast({
        title: "Error Loading Diagnostics",
        description: err.response?.data?.message || err.message || "Could not fetch telemetry traces",
        variant: "destructive",
      })
    } finally {
      setIsLoading(false)
    }
  }, [page, searchQuery, filterMode, filterStatus, toast])

  const fetchKnowledge = useCallback(async () => {
    setIsLoadingKnowledge(true)
    try {
      let res
      try {
        res = await apiClient.get("/copilot/knowledge", { params: { company_code: "all" } })
      } catch (e) {
        res = await apiClient.get("/copilot/knowledge-public", { params: { company_code: "all" } })
      }
      if (res.data?.success) {
        setKnowledgeList(res.data.data || [])
      }
    } catch (err: any) {
      console.error("Failed to load knowledge entries:", err)
    } finally {
      setIsLoadingKnowledge(false)
    }
  }, [])

  useEffect(() => {
    fetchDiagnostics()
    fetchKnowledge()
  }, [fetchDiagnostics, fetchKnowledge])

  const handleInspect = (trace: DiagnosticTrace) => {
    setSelectedTrace(trace)
    setDevStatus(trace.status)
    setDevNotes(trace.developer_notes || "")
    setIsInspectOpen(true)
  }

  const handleSaveFeedback = async () => {
    if (!selectedTrace) return
    try {
      const res = await apiClient.patch(`/copilot/diagnostics/${selectedTrace.id}/feedback`, {
        status: devStatus,
        developer_notes: devNotes,
      })
      if (res.data?.success) {
        toast({
          title: "Feedback Saved",
          description: `Trace #${selectedTrace.id} marked as ${devStatus}.`,
        })
        setSelectedTrace(res.data.data)
        fetchDiagnostics()
      }
    } catch (err: any) {
      toast({
        title: "Save Failed",
        description: err.response?.data?.message || "Could not save review feedback.",
        variant: "destructive",
      })
    }
  }

  const handleOpenPromote = (trace: DiagnosticTrace) => {
    setSelectedTrace(trace)
    setKbTopic(trace.classifier_intent ? trace.classifier_intent.toLowerCase() : "custom_guidance")
    setKbDomain("general")
    setKbTriggers(trace.prompt)
    setKbTitle(trace.final_response?.data?.title || "Operational Guidance")
    setKbSummary(trace.final_response?.message || trace.final_response?.data?.summary || "")
    
    const steps = trace.final_response?.data?.steps
    if (Array.isArray(steps)) {
      setKbSteps(steps.join("\n"))
    } else {
      setKbSteps("")
    }
    setKbNote(trace.final_response?.data?.note || "")
    setIsPromoteModalOpen(true)
  }

  const handleSavePromote = async () => {
    if (!selectedTrace) return
    try {
      const triggersArray = kbTriggers
        .split("\n")
        .map((t) => t.trim())
        .filter((t) => t.length > 0)

      const stepsArray = kbSteps
        .split("\n")
        .map((s) => s.trim())
        .filter((s) => s.length > 0)

      const payload = {
        topic: kbTopic,
        domain: kbDomain,
        trigger_keywords: triggersArray.length > 0 ? triggersArray : [selectedTrace.prompt],
        title: kbTitle,
        summary: kbSummary,
        steps: stepsArray,
        note: kbNote || null,
        actions: selectedTrace.final_response?.actions || [],
      }

      const res = await apiClient.post(`/copilot/diagnostics/${selectedTrace.id}/promote-to-guidance`, payload)
      if (res.data?.success) {
        toast({
          title: "Promoted to Knowledge Base!",
          description: "Copilot has absorbed this guidance rule and will use it in future turns.",
        })
        setIsPromoteModalOpen(false)
        fetchDiagnostics()
        fetchKnowledge()
      }
    } catch (err: any) {
      toast({
        title: "Promotion Failed",
        description: err.response?.data?.message || "Could not create knowledge base entry.",
        variant: "destructive",
      })
    }
  }

  const handleCreateKnowledgeDirect = async () => {
    try {
      const triggersArray = kbTriggers
        .split("\n")
        .map((t) => t.trim())
        .filter((t) => t.length > 0)

      const stepsArray = kbSteps
        .split("\n")
        .map((s) => s.trim())
        .filter((s) => s.length > 0)

      const payload = {
        topic: kbTopic,
        domain: kbDomain,
        trigger_keywords: triggersArray,
        title: kbTitle,
        summary: kbSummary,
        steps: stepsArray,
        note: kbNote || null,
      }

      const res = await apiClient.post("/copilot/knowledge", payload)
      if (res.data?.success) {
        toast({
          title: "Knowledge Base Rule Created",
          description: "Rule active for Copilot guidance resolution.",
        })
        setIsAddKnowledgeModalOpen(false)
        fetchKnowledge()
      }
    } catch (err: any) {
      toast({
        title: "Creation Failed",
        description: err.response?.data?.message || "Could not create knowledge rule.",
        variant: "destructive",
      })
    }
  }

  const handleDeleteKnowledge = async (id: number) => {
    try {
      const res = await apiClient.delete(`/copilot/knowledge/${id}`)
      if (res.data?.success) {
        toast({
          title: "Knowledge Entry Deleted",
          description: "Rule removed from Copilot self-learning knowledgebase.",
        })
        fetchKnowledge()
      }
    } catch (err: any) {
      toast({
        title: "Delete Failed",
        description: err.message,
        variant: "destructive",
      })
    }
  }

  const handleExport = async (format: "jsonl" | "json") => {
    try {
      const token = typeof window !== "undefined" ? localStorage.getItem("auth_token") : null
      const companyCode = typeof window !== "undefined" ? localStorage.getItem("current_company_code") : null
      const baseURL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api"
      
      const url = `${baseURL}/copilot/diagnostics/export?format=${format}${companyCode ? `&company_code=${companyCode}` : ""}`
      
      const response = await fetch(url, {
        headers: {
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...(companyCode ? { "X-Company-Code": companyCode } : {}),
        },
      })
      
      const blob = await response.blob()
      const downloadUrl = window.URL.createObjectURL(blob)
      const a = document.createElement("a")
      a.href = downloadUrl
      a.download = `copilot_diagnostics_${new Date().toISOString().slice(0, 10)}.${format}`
      document.body.appendChild(a)
      a.click()
      a.remove()

      toast({
        title: "Export Successful",
        description: `Downloaded ${format.toUpperCase()} dataset for developer agent inspection.`,
      })
    } catch (err: any) {
      toast({
        title: "Export Failed",
        description: err.message,
        variant: "destructive",
      })
    }
  }

  const handlePruneLogs = async () => {
    try {
      const res = await apiClient.delete("/copilot/diagnostics")
      if (res.data?.success) {
        toast({
          title: "Logs Cleared",
          description: `Pruned ${res.data.deleted_count} telemetry records.`,
        })
        setIsPruneModalOpen(false)
        fetchDiagnostics()
      }
    } catch (err: any) {
      toast({
        title: "Prune Failed",
        description: err.message,
        variant: "destructive",
      })
    }
  }

  const getModeBadge = (mode: string) => {
    switch (mode) {
      case "ollama":
        return <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20"><Cpu className="w-3 h-3 mr-1" /> LLM (Qwen)</Badge>
      case "parlant":
        return <Badge variant="outline" className="bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20"><Zap className="w-3 h-3 mr-1" /> Parlant</Badge>
      case "direct_action":
        return <Badge variant="outline" className="bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20"><CheckCircle2 className="w-3 h-3 mr-1" /> Direct Action</Badge>
      default:
        return <Badge variant="outline" className="bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20"><Terminal className="w-3 h-3 mr-1" /> Heuristic</Badge>
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "verified":
        return <Badge className="bg-emerald-500 text-white"><Check className="w-3 h-3 mr-1" /> Verified</Badge>
      case "needs_fix":
        return <Badge variant="destructive"><AlertTriangle className="w-3 h-3 mr-1" /> Needs Fix</Badge>
      case "promoted_to_kb":
        return <Badge className="bg-indigo-600 text-white"><Sparkles className="w-3 h-3 mr-1" /> In Knowledgebase</Badge>
      default:
        return <Badge variant="secondary"><Clock className="w-3 h-3 mr-1" /> Unreviewed</Badge>
    }
  }

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Activity className="w-7 h-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">System Diagnostics & Copilot Observability</h1>
          </div>
          <p className="text-sm text-muted-foreground mt-1">
            Real-time execution telemetry, semantic classification inspection, developer feedback loop, and self-learning knowledgebase.
          </p>
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          <Button variant="outline" size="sm" onClick={() => fetchDiagnostics()} disabled={isLoading}>
            <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? "animate-spin" : ""}`} />
            Refresh
          </Button>

          <Button variant="outline" size="sm" onClick={() => handleExport("jsonl")} className="bg-primary/5 hover:bg-primary/10">
            <FileJson className="w-4 h-4 mr-2 text-primary" />
            Export JSONL (Dev Agents)
          </Button>

          <Button variant="outline" size="sm" onClick={() => handleExport("json")}>
            <Download className="w-4 h-4 mr-2" />
            Export JSON
          </Button>

          <Button variant="ghost" size="sm" onClick={() => setIsPruneModalOpen(true)} className="text-destructive hover:bg-destructive/10">
            <Trash2 className="w-4 h-4 mr-2" />
            Prune
          </Button>
        </div>
      </div>

      {/* Telemetry Stats Overview */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        {/* 1. Total Invocations */}
        <Card 
          onClick={() => {
            setActiveTab("traces")
            setFilterStatus("all")
            setFilterMode("all")
            setSearchQuery("")
            setPage(1)
          }}
          className={`cursor-pointer transition-all duration-200 hover:shadow-md hover:border-primary/50 group ${activeTab === "traces" && filterStatus === "all" && filterMode === "all" && !searchQuery ? "ring-2 ring-primary/30 border-primary" : ""}`}
        >
          <CardHeader className="p-3.5 pb-1">
            <div className="flex items-center justify-between">
              <CardDescription className="text-xs">Total Invocations</CardDescription>
              <Activity className="w-3.5 h-3.5 text-muted-foreground group-hover:text-primary transition-colors" />
            </div>
            <CardTitle className="text-2xl font-bold">{stats?.total_traces || 0}</CardTitle>
          </CardHeader>
          <CardContent className="p-3.5 pt-0 text-[11px] text-muted-foreground flex items-center justify-between">
            <span>Captured turns</span>
            <span className="text-[10px] text-primary opacity-0 group-hover:opacity-100 transition-opacity font-medium flex items-center gap-0.5">
              All &rarr;
            </span>
          </CardContent>
        </Card>

        {/* 2. Avg Latency */}
        <Card 
          onClick={() => {
            setActiveTab("traces")
          }}
          className="cursor-pointer transition-all duration-200 hover:shadow-md hover:border-primary/50 group"
        >
          <CardHeader className="p-3.5 pb-1">
            <div className="flex items-center justify-between">
              <CardDescription className="text-xs">Avg Latency</CardDescription>
              <Clock className="w-3.5 h-3.5 text-muted-foreground group-hover:text-primary transition-colors" />
            </div>
            <CardTitle className="text-2xl font-bold">{stats?.avg_duration_ms || 0} <span className="text-xs font-normal">ms</span></CardTitle>
          </CardHeader>
          <CardContent className="p-3.5 pt-0 text-[11px] text-muted-foreground flex items-center justify-between">
            <span>Execution speed</span>
            <span className="text-[10px] text-primary opacity-0 group-hover:opacity-100 transition-opacity font-medium flex items-center gap-0.5">
              Traces &rarr;
            </span>
          </CardContent>
        </Card>

        {/* 3. Verified Quality */}
        <Card 
          onClick={() => {
            setActiveTab("traces")
            setFilterStatus("verified")
            setPage(1)
          }}
          className={`cursor-pointer transition-all duration-200 hover:shadow-md hover:border-emerald-500/50 group ${activeTab === "traces" && filterStatus === "verified" ? "ring-2 ring-emerald-500/30 border-emerald-500" : ""}`}
        >
          <CardHeader className="p-3.5 pb-1">
            <div className="flex items-center justify-between">
              <CardDescription className="text-xs">Verified Quality</CardDescription>
              <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 group-hover:scale-110 transition-transform" />
            </div>
            <CardTitle className="text-2xl font-bold text-emerald-600">{stats?.verified_count || 0}</CardTitle>
          </CardHeader>
          <CardContent className="p-3.5 pt-0 text-[11px] text-muted-foreground flex items-center justify-between">
            <span>Passed specs</span>
            <span className="text-[10px] text-emerald-600 opacity-0 group-hover:opacity-100 transition-opacity font-medium flex items-center gap-0.5">
              Filter &rarr;
            </span>
          </CardContent>
        </Card>

        {/* 4. Flagged for Fix */}
        <Card 
          onClick={() => {
            setActiveTab("traces")
            setFilterStatus("needs_fix")
            setPage(1)
          }}
          className={`cursor-pointer transition-all duration-200 hover:shadow-md hover:border-destructive/50 group ${activeTab === "traces" && filterStatus === "needs_fix" ? "ring-2 ring-destructive/30 border-destructive" : ""}`}
        >
          <CardHeader className="p-3.5 pb-1">
            <div className="flex items-center justify-between">
              <CardDescription className="text-xs">Flagged for Fix</CardDescription>
              <AlertTriangle className="w-3.5 h-3.5 text-destructive group-hover:scale-110 transition-transform" />
            </div>
            <CardTitle className="text-2xl font-bold text-destructive">{stats?.needs_fix_count || 0}</CardTitle>
          </CardHeader>
          <CardContent className="p-3.5 pt-0 text-[11px] text-muted-foreground flex items-center justify-between">
            <span>Pending dev edits</span>
            <span className="text-[10px] text-destructive opacity-0 group-hover:opacity-100 transition-opacity font-medium flex items-center gap-0.5">
              Filter &rarr;
            </span>
          </CardContent>
        </Card>

        {/* 5. In Knowledge Base */}
        <Card 
          onClick={() => {
            setActiveTab("knowledge")
            fetchKnowledge()
          }}
          className={`cursor-pointer transition-all duration-200 hover:shadow-md hover:border-indigo-500/50 group ${activeTab === "knowledge" ? "ring-2 ring-indigo-500/30 border-indigo-500" : ""}`}
        >
          <CardHeader className="p-3.5 pb-1">
            <div className="flex items-center justify-between">
              <CardDescription className="text-xs">In Knowledge Base</CardDescription>
              <BookOpen className="w-3.5 h-3.5 text-indigo-500 group-hover:scale-110 transition-transform" />
            </div>
            <CardTitle className="text-2xl font-bold text-indigo-600">{stats?.active_knowledge_entries || 0}</CardTitle>
          </CardHeader>
          <CardContent className="p-3.5 pt-0 text-[11px] text-muted-foreground flex items-center justify-between">
            <span>Self-learned rules</span>
            <span className="text-[10px] text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity font-medium flex items-center gap-0.5">
              Open tab &rarr;
            </span>
          </CardContent>
        </Card>

        {/* 6. Pending Review */}
        <Card 
          onClick={() => {
            setActiveTab("traces")
            setFilterStatus("unreviewed")
            setPage(1)
          }}
          className={`cursor-pointer transition-all duration-200 hover:shadow-md hover:border-amber-500/50 group ${activeTab === "traces" && filterStatus === "unreviewed" ? "ring-2 ring-amber-500/30 border-amber-500" : ""}`}
        >
          <CardHeader className="p-3.5 pb-1">
            <div className="flex items-center justify-between">
              <CardDescription className="text-xs">Pending Review</CardDescription>
              <Clock className="w-3.5 h-3.5 text-amber-500 group-hover:scale-110 transition-transform" />
            </div>
            <CardTitle className="text-2xl font-bold text-amber-600">{stats?.unreviewed_count || 0}</CardTitle>
          </CardHeader>
          <CardContent className="p-3.5 pt-0 text-[11px] text-muted-foreground flex items-center justify-between">
            <span>Awaiting inspection</span>
            <span className="text-[10px] text-amber-600 opacity-0 group-hover:opacity-100 transition-opacity font-medium flex items-center gap-0.5">
              Filter &rarr;
            </span>
          </CardContent>
        </Card>
      </div>

      {/* Main Tabs */}
      <Tabs value={activeTab} onValueChange={(v: any) => setActiveTab(v)} className="space-y-4">
        <div className="flex items-center justify-between border-b pb-2">
          <TabsList>
            <TabsTrigger value="traces" className="gap-2">
              <Activity className="w-4 h-4" />
              Diagnostic Traces & Telemetry
            </TabsTrigger>
            <TabsTrigger value="knowledge" className="gap-2">
              <BookOpen className="w-4 h-4" />
              Self-Learning Knowledge Base ({knowledgeList.length})
            </TabsTrigger>
          </TabsList>

          {activeTab === "knowledge" && (
            <Button size="sm" onClick={() => {
              setKbTopic("")
              setKbDomain("general")
              setKbTriggers("")
              setKbTitle("")
              setKbSummary("")
              setKbSteps("")
              setKbNote("")
              setIsAddKnowledgeModalOpen(true)
            }}>
              <Plus className="w-4 h-4 mr-2" />
              Add Knowledge Rule
            </Button>
          )}
        </div>

        {/* Tab 1: Diagnostic Traces */}
        <TabsContent value="traces" className="space-y-4">
          {/* Filters Bar */}
          <div className="flex flex-col md:flex-row items-center gap-3">
            <div className="relative flex-1 w-full">
              <Search className="w-4 h-4 absolute left-3 top-3 text-muted-foreground" />
              <Input
                placeholder="Search prompt, intent, dispatched capability, or notes..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>

            <Select value={filterMode} onValueChange={setFilterMode}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Classifier Mode" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Modes</SelectItem>
                <SelectItem value="ollama">LLM (Qwen Ollama)</SelectItem>
                <SelectItem value="parlant">Parlant Dialogue</SelectItem>
                <SelectItem value="heuristic">Heuristic Fallback</SelectItem>
                <SelectItem value="direct_action">Direct Actions</SelectItem>
                <SelectItem value="deep_link">Deep Link (Pre-resolved)</SelectItem>
              </SelectContent>
            </Select>

            <Select value={filterStatus} onValueChange={setFilterStatus}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Review Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem value="unreviewed">Unreviewed</SelectItem>
                <SelectItem value="verified">Verified</SelectItem>
                <SelectItem value="needs_fix">Needs Fix</SelectItem>
                <SelectItem value="promoted_to_kb">In Knowledgebase</SelectItem>
                <SelectItem value="anomaly">⚠️ Anomaly Flagged</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Traces Table */}
          <div className="border rounded-lg overflow-hidden bg-card">
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="text-xs uppercase bg-muted/50 border-b text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3">Timestamp / Ref</th>
                    <th className="px-4 py-3">User Prompt Asked</th>
                    <th className="px-4 py-3">Mode</th>
                    <th className="px-4 py-3">Intent & Confidence</th>
                    <th className="px-4 py-3">Dispatched Capability</th>
                    <th className="px-4 py-3">Latency</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {traces.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="px-4 py-8 text-center text-muted-foreground">
                        {isLoading ? "Loading traces..." : "No diagnostic traces recorded yet. Ask questions in the Copilot chat widget to generate telemetry."}
                      </td>
                    </tr>
                  ) : (
                    traces.map((trace) => (
                      <tr key={trace.id} className="hover:bg-muted/30 transition-colors cursor-pointer" onClick={() => handleInspect(trace)}>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="font-mono text-xs">#{trace.id}</div>
                          <div className="text-[11px] text-muted-foreground">
                            {new Date(trace.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                          </div>
                        </td>
                        <td className="px-4 py-3 max-w-xs truncate font-medium">
                          {trace.prompt || <span className="text-muted-foreground italic">(Direct interactive click)</span>}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          {getModeBadge(trace.classifier_mode)}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="font-semibold text-xs">{trace.classifier_intent || "N/A"}</div>
                          {trace.classifier_confidence !== null && (
                            <div className="text-[11px] text-muted-foreground">
                              {Math.round(trace.classifier_confidence * 100)}% conf
                            </div>
                          )}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap font-mono text-xs text-muted-foreground">
                          {trace.dispatched_action || "default"}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <span className="font-mono text-xs">{trace.duration_ms || 0}ms</span>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="flex flex-col gap-1">
                            {getStatusBadge(trace.status)}
                            {trace.anomaly_flag && (
                              <Badge variant="outline" className="bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20 text-[10px]">
                                <AlertTriangle className="w-2.5 h-2.5 mr-0.5" />
                                {trace.anomaly_flag.replace(/_/g, ' ')}
                              </Badge>
                            )}
                            {trace.entry_point === 'deep_link' && (
                              <Badge variant="outline" className="bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20 text-[10px]">
                                <ExternalLink className="w-2.5 h-2.5 mr-0.5" />
                                deep link
                              </Badge>
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-3 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                          <div className="flex items-center justify-end gap-1">
                            <Button variant="ghost" size="icon" onClick={() => handleInspect(trace)} title="Inspect Trace">
                              <Eye className="w-4 h-4" />
                            </Button>
                            {trace.entry_point !== 'deep_link' && trace.classifier_mode !== 'direct_action' && (
                              <Button variant="ghost" size="icon" onClick={() => handleOpenPromote(trace)} title="Promote to Knowledge Base">
                                <Sparkles className="w-4 h-4 text-indigo-500" />
                              </Button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-between p-4 border-t bg-muted/20">
                <div className="text-xs text-muted-foreground">
                  Page {page} of {totalPages}
                </div>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1}>
                    Previous
                  </Button>
                  <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.min(totalPages, p + 1))} disabled={page === totalPages}>
                    Next
                  </Button>
                </div>
              </div>
            )}
          </div>
        </TabsContent>

        {/* Tab 2: Knowledgebase */}
        <TabsContent value="knowledge" className="space-y-4">
          {isLoadingKnowledge ? (
            <div className="border rounded-lg p-12 text-center text-muted-foreground flex flex-col items-center justify-center gap-3">
              <RefreshCw className="w-6 h-6 animate-spin text-primary" />
              <p className="text-sm font-medium">Loading self-learned knowledge rules...</p>
            </div>
          ) : knowledgeList.length === 0 ? (
            <div className="col-span-full border border-dashed rounded-lg p-12 text-center text-muted-foreground">
              <BookOpen className="w-8 h-8 mx-auto mb-2 opacity-50 text-indigo-500" />
              <h3 className="font-semibold text-foreground text-base">No Self-Learned Knowledge Rules Yet</h3>
              <p className="text-sm mt-1 max-w-md mx-auto">
                Promote diagnostic traces from the Traces tab or click <strong>"Add Knowledge Rule"</strong> to train Copilot with custom ERP workflows and company policies.
              </p>
              <Button size="sm" className="mt-4" onClick={() => setIsAddKnowledgeModalOpen(true)}>
                <Plus className="w-4 h-4 mr-1.5" />
                Add First Knowledge Rule
              </Button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {knowledgeList.map((kb) => (
                <Card key={kb.id} className="flex flex-col justify-between border hover:shadow-md transition-shadow">
                  <CardHeader className="p-4 pb-2">
                    <div className="flex items-center justify-between gap-2">
                      <div className="flex items-center gap-1.5 flex-wrap">
                        <Badge variant="outline" className="text-[11px] font-semibold capitalize bg-primary/5 text-primary border-primary/20">
                          {kb.domain || "general"}
                        </Badge>
                        <Badge variant="secondary" className="text-[10px] font-mono">
                          {kb.company_code ? `Tenant: ${kb.company_code}` : "Universal (All Tenants)"}
                        </Badge>
                      </div>
                      <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:bg-destructive/10" onClick={() => handleDeleteKnowledge(kb.id)} title="Delete Rule">
                        <Trash2 className="w-3.5 h-3.5" />
                      </Button>
                    </div>
                    <CardTitle className="text-base font-semibold mt-2 line-clamp-1">{kb.title}</CardTitle>
                    <CardDescription className="text-xs font-mono text-muted-foreground flex items-center gap-1">
                      <Sparkles className="w-3 h-3 text-indigo-500" /> Topic: {kb.topic}
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="p-4 pt-2 space-y-3 flex-1">
                    <p className="text-xs text-muted-foreground line-clamp-3 leading-relaxed">{kb.summary}</p>

                    <div>
                      <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-1">Trigger Phrases:</div>
                      <div className="flex flex-wrap gap-1">
                        {Array.isArray(kb.trigger_keywords) && kb.trigger_keywords.map((trig, idx) => (
                          <Badge key={idx} variant="secondary" className="text-[10px] font-normal bg-muted text-foreground">
                            "{trig}"
                          </Badge>
                        ))}
                      </div>
                    </div>

                    {Array.isArray(kb.steps) && kb.steps.length > 0 && (
                      <div className="bg-muted/50 p-2.5 rounded-md text-[11px] space-y-1 font-mono border border-border/40">
                        <div className="font-semibold text-foreground flex items-center justify-between">
                          <span>Workflow Steps:</span>
                          <span className="text-[10px] text-muted-foreground font-normal">{kb.steps.length} total</span>
                        </div>
                        {kb.steps.slice(0, 3).map((s, i) => (
                          <div key={i} className="truncate text-muted-foreground">&bull; {s}</div>
                        ))}
                      </div>
                    )}

                    {kb.note && (
                      <div className="text-[11px] italic text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 p-2 rounded border border-amber-200/40">
                        💡 {kb.note}
                      </div>
                    )}
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </TabsContent>
      </Tabs>

      {/* Inspect Modal Drawer */}
      <Dialog open={isInspectOpen} onOpenChange={(open) => {
        setIsInspectOpen(open)
        if (!open) setIsInspectMaximized(false)
      }}>
        <DialogContent className={`transition-all duration-200 overflow-hidden flex flex-col p-6 border shadow-2xl ${isInspectMaximized ? "max-w-[99vw] w-[99vw] h-[98vh] max-h-[98vh]" : "max-w-6xl w-[95vw] max-h-[92vh]"}`}>
          <DialogHeader className="pb-3 border-b shrink-0">
            <div className="flex items-center justify-between gap-4">
              <div>
                <DialogTitle className="text-xl flex items-center gap-2">
                  Trace #{selectedTrace?.id} Inspector
                  {selectedTrace && getModeBadge(selectedTrace.classifier_mode)}
                </DialogTitle>
                <DialogDescription className="mt-1">
                  Session: <span className="font-mono">{selectedTrace?.session_id || "default"}</span> | Company: <span className="font-semibold">{selectedTrace?.company_code || "MAIN"}</span> | Latency: <span className="font-mono text-primary font-semibold">{selectedTrace?.duration_ms}ms</span>
                </DialogDescription>
              </div>
              <div className="flex items-center gap-2">
                {selectedTrace && getStatusBadge(selectedTrace.status)}
                <Button
                  variant="outline"
                  size="icon"
                  className="h-8 w-8 ml-1 text-muted-foreground hover:text-foreground"
                  onClick={() => setIsInspectMaximized(!isInspectMaximized)}
                  title={isInspectMaximized ? "Restore Default Size" : "Full Screen Inspector"}
                >
                  {isInspectMaximized ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4 text-primary" />}
                </Button>
              </div>
            </div>
          </DialogHeader>

          {selectedTrace && (
            <Tabs defaultValue="output" className="space-y-4 pt-2 flex-1 flex flex-col min-h-0 overflow-hidden">
              <div className="bg-muted/80 p-1 rounded-xl border border-border/60 shrink-0">
                <TabsList className="flex w-full bg-transparent p-0 h-auto gap-1">
                  <TabsTrigger 
                    value="output" 
                    className="flex-1 py-2 px-3 text-xs font-semibold gap-1.5 rounded-lg data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm transition-all"
                  >
                    <Terminal className="w-3.5 h-3.5 text-primary" />
                    Replay & Output
                  </TabsTrigger>
                  <TabsTrigger 
                    value="classifier" 
                    className="flex-1 py-2 px-3 text-xs font-semibold gap-1.5 rounded-lg data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm transition-all"
                  >
                    <Cpu className="w-3.5 h-3.5 text-indigo-500" />
                    Classification & Logic
                  </TabsTrigger>
                  <TabsTrigger 
                    value="context" 
                    className="flex-1 py-2 px-3 text-xs font-semibold gap-1.5 rounded-lg data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm transition-all"
                  >
                    <Layers className="w-3.5 h-3.5 text-amber-500" />
                    State & Decay
                  </TabsTrigger>
                  <TabsTrigger 
                    value="feedback" 
                    className="flex-1 py-2 px-3 text-xs font-semibold gap-1.5 rounded-lg data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm transition-all"
                  >
                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />
                    Developer Review
                  </TabsTrigger>
                </TabsList>
              </div>

              {/* Sub-tab 1: Turn Replay & Output */}
              <TabsContent value="output" className="space-y-4 flex-1 overflow-y-auto pr-1">
                <div className="space-y-2">
                  <div className="text-xs font-semibold text-muted-foreground uppercase">User Prompt:</div>
                  <div className="p-3 bg-muted rounded-lg font-medium text-sm">
                    {selectedTrace.prompt || "<Interactive Click Action>"}
                  </div>
                </div>

                <div className="space-y-2">
                  <div className="text-xs font-semibold text-muted-foreground uppercase">Copilot Response Text:</div>
                  <div className="p-3 bg-primary/5 border border-primary/20 rounded-lg text-sm whitespace-pre-wrap">
                    {selectedTrace.final_response?.message || "No text message"}
                  </div>
                </div>

                {selectedTrace.final_response?.card_type && (
                  <div className="space-y-2">
                    <div className="text-xs font-semibold text-muted-foreground uppercase">
                      Card Rendered: <Badge variant="outline">{selectedTrace.final_response.card_type}</Badge>
                    </div>
                    <pre className="p-3 bg-muted/60 rounded text-xs font-mono overflow-x-auto max-h-56 border">
                      {JSON.stringify(selectedTrace.final_response.data || selectedTrace.final_response, null, 2)}
                    </pre>
                  </div>
                )}

                {Array.isArray(selectedTrace.final_response?.actions) && selectedTrace.final_response.actions.length > 0 && (
                  <div className="space-y-2">
                    <div className="text-xs font-semibold text-muted-foreground uppercase">Action Buttons Presented:</div>
                    <div className="flex flex-wrap gap-2">
                      {selectedTrace.final_response.actions.map((act: any, i: number) => (
                        <Button key={i} variant="outline" size="sm" className="text-xs pointer-events-none">
                          {act.label} ({act.action})
                        </Button>
                      ))}
                    </div>
                  </div>
                )}
              </TabsContent>

              {/* Sub-tab 2: Classifier Diagnostics */}
              <TabsContent value="classifier" className="space-y-4 flex-1 overflow-y-auto pr-1">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                  <div className="p-3 border rounded">
                    <div className="text-[11px] text-muted-foreground">Classifier Mode</div>
                    <div className="font-semibold text-sm capitalize">{selectedTrace.classifier_mode}</div>
                  </div>
                  <div className="p-3 border rounded">
                    <div className="text-[11px] text-muted-foreground">Detected Intent</div>
                    <div className="font-semibold text-sm">{selectedTrace.classifier_intent || "N/A"}</div>
                  </div>
                  <div className="p-3 border rounded">
                    <div className="text-[11px] text-muted-foreground">Confidence Score</div>
                    <div className="font-semibold text-sm">
                      {selectedTrace.classifier_confidence !== null ? `${Math.round(selectedTrace.classifier_confidence * 100)}%` : "N/A"}
                    </div>
                  </div>
                </div>

                {selectedTrace.safety_evaluations && (
                  <div className="p-3 bg-amber-500/10 border border-amber-500/20 rounded text-xs space-y-1">
                    <div className="font-semibold text-amber-600 flex items-center gap-1">
                      <ShieldAlert className="w-4 h-4" /> Safety Evaluation Triggered
                    </div>
                    <pre className="font-mono text-[11px]">
                      {JSON.stringify(selectedTrace.safety_evaluations, null, 2)}
                    </pre>
                  </div>
                )}

                <div className="space-y-2">
                  <div className="text-xs font-semibold text-muted-foreground uppercase">Extracted Arguments & Classification JSON:</div>
                  <pre className="p-3 bg-muted rounded font-mono text-xs overflow-x-auto max-h-60">
                    {JSON.stringify(selectedTrace.classifier_output || {}, null, 2)}
                  </pre>
                </div>
              </TabsContent>

              {/* Sub-tab 3: Context State & Decay */}
              <TabsContent value="context" className="space-y-4 flex-1 overflow-y-auto pr-1">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <div className="text-xs font-semibold text-muted-foreground uppercase">Context Before Turn:</div>
                    <pre className="p-3 bg-muted rounded font-mono text-xs overflow-x-auto max-h-72 border">
                      {JSON.stringify(selectedTrace.context_before || {}, null, 2)}
                    </pre>
                  </div>
                  <div className="space-y-2">
                    <div className="text-xs font-semibold text-muted-foreground uppercase">Context After Turn:</div>
                    <pre className="p-3 bg-muted rounded font-mono text-xs overflow-x-auto max-h-72 border">
                      {JSON.stringify(selectedTrace.context_after || {}, null, 2)}
                    </pre>
                  </div>
                </div>
              </TabsContent>

              {/* Sub-tab 4: Developer Feedback */}
              <TabsContent value="feedback" className="space-y-4 flex-1 overflow-y-auto pr-1">
                <div className="space-y-2">
                  <label className="text-xs font-semibold uppercase">Review Quality Status</label>
                  <Select value={devStatus} onValueChange={(v: any) => setDevStatus(v)}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select review status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="unreviewed">Unreviewed</SelectItem>
                      <SelectItem value="verified">Verified Correct (Passes Spec)</SelectItem>
                      <SelectItem value="needs_fix">Needs Fix / Flagged (Classifier/Capability Gap)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <label className="text-xs font-semibold uppercase">Developer Notes / Issue Annotation</label>
                  <Textarea
                    placeholder="Document why this intent was missed, entity extraction notes, or test requirements..."
                    value={devNotes}
                    onChange={(e) => setDevNotes(e.target.value)}
                    rows={4}
                  />
                </div>

                <div className="flex items-center justify-between pt-2">
                  {selectedTrace.entry_point !== 'deep_link' && selectedTrace.classifier_mode !== 'direct_action' && (
                    <Button variant="outline" onClick={() => handleOpenPromote(selectedTrace)}>
                      <Sparkles className="w-4 h-4 mr-2 text-indigo-500" />
                      Promote to Guidance Knowledge Base
                    </Button>
                  )}

                  <Button onClick={handleSaveFeedback}>
                    Save Feedback & Notes
                  </Button>
                </div>
              </TabsContent>
            </Tabs>
          )}
        </DialogContent>
      </Dialog>

      {/* Promote to Knowledgebase Modal */}
      <Dialog open={isPromoteModalOpen} onOpenChange={setIsPromoteModalOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Sparkles className="w-5 h-5 text-indigo-500" />
              Promote to Self-Learning Knowledge Base
            </DialogTitle>
            <DialogDescription>
              Convert this interaction into a permanent guidance rule. Future matching queries will resolve this answer instantly.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-semibold">Topic Identifier</label>
                <Input value={kbTopic} onChange={(e) => setKbTopic(e.target.value)} placeholder="e.g. advance_payment_policy" />
              </div>
              <div className="space-y-1">
                <label className="text-xs font-semibold">Domain</label>
                <Select value={kbDomain} onValueChange={setKbDomain}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="general">General</SelectItem>
                    <SelectItem value="voucher">Voucher / Transactions</SelectItem>
                    <SelectItem value="coa">Chart of Accounts</SelectItem>
                    <SelectItem value="reports">Reports</SelectItem>
                    <SelectItem value="periods">Fiscal Periods</SelectItem>
                    <SelectItem value="opening_balance">Opening Balances</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Trigger Phrases (one per line)</label>
              <Textarea
                value={kbTriggers}
                onChange={(e) => setKbTriggers(e.target.value)}
                placeholder="how to claim petty cash&#10;petty cash reimbursement"
                rows={3}
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Guidance Title</label>
              <Input value={kbTitle} onChange={(e) => setKbTitle(e.target.value)} placeholder="Petty Cash Claim & Reimbursement Workflow" />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Summary / Explanation</label>
              <Textarea value={kbSummary} onChange={(e) => setKbSummary(e.target.value)} rows={3} />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Operational Steps (one per line)</label>
              <Textarea
                value={kbSteps}
                onChange={(e) => setKbSteps(e.target.value)}
                placeholder="1. Attach receipt&#10;2. Department Lead signs off&#10;3. Post Petty Cash voucher"
                rows={3}
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Compliance Note (optional)</label>
              <Input value={kbNote} onChange={(e) => setKbNote(e.target.value)} placeholder="e.g. Transactions over Rs. 100k require dual maker-checker authorization." />
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setIsPromoteModalOpen(false)}>Cancel</Button>
            <Button onClick={handleSavePromote} className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Save & Activate Rule
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Add Knowledge Direct Modal */}
      <Dialog open={isAddKnowledgeModalOpen} onOpenChange={setIsAddKnowledgeModalOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Plus className="w-5 h-5 text-primary" />
              Add Knowledge Base Rule
            </DialogTitle>
            <DialogDescription>
              Teach Copilot new ERP workflows and operational procedures.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-semibold">Topic Identifier</label>
                <Input value={kbTopic} onChange={(e) => setKbTopic(e.target.value)} placeholder="e.g. inventory_writeoff_policy" />
              </div>
              <div className="space-y-1">
                <label className="text-xs font-semibold">Domain</label>
                <Select value={kbDomain} onValueChange={setKbDomain}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="general">General</SelectItem>
                    <SelectItem value="voucher">Voucher / Transactions</SelectItem>
                    <SelectItem value="coa">Chart of Accounts</SelectItem>
                    <SelectItem value="reports">Reports</SelectItem>
                    <SelectItem value="periods">Fiscal Periods</SelectItem>
                    <SelectItem value="opening_balance">Opening Balances</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Trigger Phrases (one per line)</label>
              <Textarea
                value={kbTriggers}
                onChange={(e) => setKbTriggers(e.target.value)}
                placeholder="how to write off damaged goods&#10;damaged inventory policy"
                rows={3}
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Guidance Title</label>
              <Input value={kbTitle} onChange={(e) => setKbTitle(e.target.value)} placeholder="Inventory Write-off & Disposal Procedure" />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Summary / Explanation</label>
              <Textarea value={kbSummary} onChange={(e) => setKbSummary(e.target.value)} rows={3} />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Operational Steps (one per line)</label>
              <Textarea
                value={kbSteps}
                onChange={(e) => setKbSteps(e.target.value)}
                placeholder="1. Complete physical inspection report&#10;2. Debit Inventory Loss (6210)&#10;3. Credit Inventory Asset (1200)"
                rows={3}
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold">Compliance Note (optional)</label>
              <Input value={kbNote} onChange={(e) => setKbNote(e.target.value)} placeholder="e.g. Requires CFO sign-off if amount exceeds Rs. 500,000." />
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setIsAddKnowledgeModalOpen(false)}>Cancel</Button>
            <Button onClick={handleCreateKnowledgeDirect}>Create Rule</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Prune Confirmation Modal */}
      <Dialog open={isPruneModalOpen} onOpenChange={setIsPruneModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-destructive">
              <Trash2 className="w-5 h-5" />
              Prune Diagnostic Telemetry Logs?
            </DialogTitle>
            <DialogDescription>
              This will permanently delete recorded traces. Custom knowledgebase rules will NOT be deleted.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsPruneModalOpen(false)}>Cancel</Button>
            <Button variant="destructive" onClick={handlePruneLogs}>Clear Telemetry Logs</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
