"use client"

import { useState, useRef, useEffect } from "react"
import apiClient from "@/lib/api-client"
import {
  Bot,
  Sparkles,
  X,
  Send,
  Loader2,
  CheckCircle2,
  AlertCircle,
  TrendingUp,
  FileText,
  Search,
  Maximize2,
  Minimize2,
  ChevronRight,
  ShieldCheck,
  Wallet,
  BookOpen,
  User,
  ExternalLink,
  Printer,
  RotateCcw,
  Building2,
  Layers,
} from "lucide-react"

interface Message {
  id: string
  sender: "user" | "taliya"
  text: string
  cardType?: string
  data?: any
  actions?: Array<{
    label: string
    action: string
    payload: any
    variant?: string
  }>
  timestamp: string
}

export default function CopilotWidget({ companyCode }: { companyCode?: string }) {
  const [isOpen, setIsOpen] = useState(false)
  const [isExpanded, setIsExpanded] = useState(false)
  const [input, setInput] = useState("")
  const [loading, setLoading] = useState(false)
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "welcome",
      sender: "taliya",
      text: "Hello! I am **Taliya**, your Alamia Accounts Copilot backed by Alamia 360.\n\nI can help you query reports, look up vouchers & accounts (e.g. *\"Tell me about voucher OB-2026-001\"* or *\"What is the balance of Meezan Bank?\"*), and draft balanced double-entry vouchers.",
      timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
    },
  ])

  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight
    }
  }, [messages, isOpen])

  const sendMessage = async (promptText: string, contextOverride?: any) => {
    if (!promptText && !contextOverride) return

    const userMsg: Message = {
      id: `user-${Date.now()}`,
      sender: "user",
      text: promptText,
      timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
    }

    if (promptText) {
      setMessages((prev) => [...prev, userMsg])
    }
    setInput("")
    setLoading(true)

    try {
      const response = await apiClient.post("/copilot/chat", {
        prompt: promptText,
        company_code: companyCode,
        context: contextOverride || {},
      })

      const reply = response.data?.data
      if (reply) {
        const assistantMsg: Message = {
          id: `taliya-${Date.now()}`,
          sender: "taliya",
          text: reply.message || "Capability executed successfully.",
          cardType: reply.card_type,
          data: reply.data,
          actions: reply.actions,
          timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
        }
        setMessages((prev) => [...prev, assistantMsg])
      }
    } catch (err: any) {
      const errorMsg: Message = {
        id: `err-${Date.now()}`,
        sender: "taliya",
        text:
          err.response?.data?.message ||
          err.response?.data?.error ||
          "Sorry, could not process this request right now.",
        cardType: "error",
        timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
      }
      setMessages((prev) => [...prev, errorMsg])
    } finally {
      setLoading(false)
    }
  }

  const handleActionClick = (actionItem: any) => {
    if (actionItem.action === "post_voucher") {
      sendMessage("", { action: "post_voucher", voucher: actionItem.payload })
    } else if (actionItem.action === "navigate_page") {
      if (typeof window !== "undefined") {
        window.dispatchEvent(
          new CustomEvent("copilot:navigate", {
            detail: actionItem.payload,
          })
        )
      }
    } else if (actionItem.action === "draft_prompt") {
      if (actionItem.payload?.prompt) {
        sendMessage(actionItem.payload.prompt)
      }
    } else if (actionItem.action === "print_voucher") {
      if (typeof window !== "undefined") {
        window.dispatchEvent(
          new CustomEvent("copilot:navigate", {
            detail: { page: "voucher-view", type: "voucher", id: actionItem.payload?.voucher?.reference, rawItem: actionItem.payload?.voucher, print: true },
          })
        )
      }
    } else if (actionItem.action === "reverse_voucher") {
      if (typeof window !== "undefined") {
        window.dispatchEvent(
          new CustomEvent("copilot:navigate", {
            detail: { page: "daybook", action: "reverse", reference: actionItem.payload?.reference },
          })
        )
      }
    }
  }

  const quickPrompts = [
    { label: "📄 Voucher OB-2026-001", text: "Tell me about voucher OB-2026-001" },
    { label: "🏦 Meezan Bank Balance", text: "What is the balance of Meezan Bank?" },
    { label: "📊 Trial Balance", text: "Show Trial Balance summary" },
    { label: "📝 Draft Voucher", text: "Paid Rs. 25,000 for office supplies via Meezan Bank" },
  ]

  return (
    <>
      {/* Floating Trigger Button */}
      {!isOpen && (
        <button
          onClick={() => setIsOpen(true)}
          className="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-3 bg-primary text-primary-foreground rounded-full shadow-2xl hover:opacity-95 transition-all duration-200 group border border-primary/20 hover:scale-105"
          title="Open Alamia AI Copilot (Taliya)"
        >
          <div className="relative">
            <Sparkles className="w-5 h-5 text-amber-300 animate-pulse" />
          </div>
          <span className="font-semibold text-sm tracking-wide">Taliya Copilot</span>
          <span className="flex h-2 w-2 relative">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
          </span>
        </button>
      )}

      {/* Copilot Drawer / Chat Window */}
      {isOpen && (
        <div
          className={`fixed bottom-6 right-6 z-50 bg-card border border-border shadow-2xl rounded-2xl flex flex-col overflow-hidden transition-all duration-200 ${
            isExpanded ? "w-[680px] h-[780px]" : "w-[430px] h-[580px]"
          }`}
        >
          {/* Header */}
          <div className="px-4 py-3 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border-b border-border flex items-center justify-between">
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-primary-foreground shadow-sm">
                <Bot className="w-4 h-4" />
              </div>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-semibold text-sm text-foreground">Taliya</span>
                  <span className="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-primary/15 text-primary border border-primary/20">
                    Alamia 360
                  </span>
                </div>
                <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                  <ShieldCheck className="w-3 h-3 text-emerald-500" />
                  <span>Double-entry validated</span>
                  {companyCode && (
                    <span className="font-mono text-[10px] uppercase font-semibold">({companyCode})</span>
                  )}
                </div>
              </div>
            </div>

            <div className="flex items-center gap-1">
              <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors"
                title={isExpanded ? "Collapse" : "Expand"}
              >
                {isExpanded ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
              </button>
              <button
                onClick={() => setIsOpen(false)}
                className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors"
                title="Close"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          </div>

          {/* Quick Action Prompt Chips */}
          <div className="px-3 py-2 bg-muted/30 border-b border-border flex items-center gap-1.5 overflow-x-auto scrollbar-none text-xs">
            {quickPrompts.map((p, idx) => (
              <button
                key={idx}
                onClick={() => sendMessage(p.text)}
                disabled={loading}
                className="whitespace-nowrap px-2.5 py-1 rounded-full bg-background border border-border hover:border-primary/50 text-foreground hover:text-primary transition-colors text-[11px] font-medium flex-shrink-0 shadow-2xs"
              >
                {p.label}
              </button>
            ))}
          </div>

          {/* Message List */}
          <div ref={scrollRef} className="flex-1 p-4 overflow-y-auto space-y-4">
            {messages.map((m) => (
              <div
                key={m.id}
                className={`flex flex-col ${m.sender === "user" ? "items-end" : "items-start"}`}
              >
                <div
                  className={`max-w-[88%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed ${
                    m.sender === "user"
                      ? "bg-primary text-primary-foreground rounded-br-xs"
                      : "bg-muted text-foreground border border-border/60 rounded-bl-xs"
                  }`}
                >
                  <p className="whitespace-pre-line">{m.text}</p>

                  {/* 1. Voucher Brief Card */}
                  {m.cardType === "voucher_brief" && m.data && (
                    <div className="mt-3 p-3 bg-background border border-border rounded-xl text-foreground text-xs space-y-2.5 shadow-xs">
                      <div className="flex items-center justify-between font-semibold border-b border-border pb-1.5">
                        <div className="flex items-center gap-1.5">
                          <FileText className="w-4 h-4 text-primary" />
                          <span className="text-primary font-mono text-xs">{m.data.reference}</span>
                          <span className="text-[10px] px-1.5 py-0.2 rounded bg-primary/10 text-primary uppercase font-bold">
                            {m.data.voucher_type}
                          </span>
                        </div>
                        <span className="text-[11px] text-muted-foreground">{m.data.date}</span>
                      </div>

                      {m.data.description && (
                        <p className="text-[11px] text-muted-foreground italic">
                          &ldquo;{m.data.description}&rdquo;
                        </p>
                      )}

                      {/* Line Items Preview */}
                      {m.data.line_items && m.data.line_items.length > 0 && (
                        <div className="divide-y divide-border/60 border border-border/60 rounded-md overflow-hidden bg-muted/20">
                          {m.data.line_items.slice(0, 4).map((line: any, lIdx: number) => (
                            <div key={lIdx} className="px-2.5 py-1.5 flex justify-between items-center text-[11px]">
                              <div className="truncate max-w-[65%]">
                                <span className="font-mono font-semibold">[{line.account_code}]</span>{" "}
                                <span className="text-muted-foreground">{line.account_name}</span>
                              </div>
                              <div className="font-mono font-medium shrink-0">
                                {line.debit > 0 ? (
                                  <span className="text-emerald-600 dark:text-emerald-400 font-semibold">Dr Rs. {line.debit.toLocaleString()}</span>
                                ) : (
                                  <span className="text-blue-600 dark:text-blue-400 font-semibold">Cr Rs. {line.credit.toLocaleString()}</span>
                                )}
                              </div>
                            </div>
                          ))}
                          {m.data.line_items.length > 4 && (
                            <div className="px-2.5 py-1 text-[10px] text-center text-muted-foreground bg-muted/40 italic">
                              +{m.data.line_items.length - 4} more posting legs
                            </div>
                          )}
                        </div>
                      )}

                      <div className="flex items-center justify-between pt-1 border-t border-border/50 text-[11px]">
                        <span className="text-muted-foreground">Total Posting:</span>
                        <span className="font-mono font-bold text-foreground">
                          Rs. {(m.data.total_debit || 0).toLocaleString()}
                        </span>
                      </div>
                    </div>
                  )}

                  {/* 2. Account Brief Card */}
                  {m.cardType === "account_brief" && m.data && (
                    <div className="mt-3 p-3 bg-background border border-border rounded-xl text-foreground text-xs space-y-2 shadow-xs">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1.5">
                          <Wallet className="w-4 h-4 text-primary" />
                          <span className="font-mono font-bold text-xs">[{m.data.code}]</span>
                          <span className="font-semibold text-foreground truncate max-w-[180px]">{m.data.name}</span>
                        </div>
                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary uppercase">
                          {m.data.account_type}
                        </span>
                      </div>

                      <div className="p-2.5 rounded-lg bg-muted/40 border border-border/50 flex justify-between items-center">
                        <div>
                          <p className="text-[10px] uppercase font-medium text-muted-foreground">Current Ledger Balance</p>
                          <p className="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                            {m.data.currency} {Number(m.data.balance || 0).toLocaleString()}
                          </p>
                        </div>
                        <div className="text-right text-[10px] text-muted-foreground">
                          <span className="px-1.5 py-0.5 rounded bg-muted font-medium">
                            {m.data.category ? "Folder Category" : "Posting Leaf"}
                          </span>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* 3. Disambiguation Options Card */}
                  {m.cardType === "disambiguation" && m.data?.options && (
                    <div className="mt-3 space-y-1.5">
                      <p className="text-[11px] font-semibold text-muted-foreground uppercase px-1">
                        Select a record to view details:
                      </p>
                      <div className="space-y-1">
                        {m.data.options.map((opt: any, optIdx: number) => (
                          <button
                            key={optIdx}
                            type="button"
                            onClick={() => sendMessage(opt.prompt, { action: "view_entity", entity_type: opt.type, voucher: opt.type === "voucher" ? opt.raw : undefined, account: opt.type === "account" ? opt.raw : undefined })}
                            className="w-full p-2 text-left bg-background hover:bg-accent border border-border hover:border-primary/50 rounded-lg text-xs transition-all flex items-center justify-between group cursor-pointer shadow-2xs"
                          >
                            <div className="flex items-center gap-2 min-w-0 flex-1">
                              <div className="p-1 rounded bg-muted text-muted-foreground group-hover:text-primary group-hover:bg-primary/10 transition-colors shrink-0">
                                {opt.type === "voucher" && <FileText className="w-3.5 h-3.5" />}
                                {opt.type === "account" && <Wallet className="w-3.5 h-3.5" />}
                                {opt.type === "user" && <User className="w-3.5 h-3.5" />}
                              </div>
                              <div className="min-w-0 flex-1">
                                <p className="font-semibold text-foreground truncate group-hover:text-primary transition-colors text-[11px]">
                                  {opt.label}
                                </p>
                                {opt.subtitle && (
                                  <p className="text-[10px] text-muted-foreground truncate">
                                    {opt.subtitle}
                                  </p>
                                )}
                              </div>
                            </div>
                            <ChevronRight className="w-3.5 h-3.5 text-muted-foreground group-hover:text-primary shrink-0 transition-transform group-hover:translate-x-0.5" />
                          </button>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* 4. Voucher Draft Card */}
                  {m.cardType === "voucher_draft" && m.data?.voucher && (
                    <div className="mt-3 p-3 bg-background border border-border rounded-xl text-foreground text-xs space-y-2.5">
                      <div className="flex items-center justify-between font-semibold border-b border-border pb-1.5">
                        <span className="text-primary font-mono">{m.data.voucher.reference}</span>
                        <span className="text-[10px] text-muted-foreground">{m.data.voucher.date}</span>
                      </div>
                      <div className="space-y-1">
                        <div className="text-[11px] text-muted-foreground">{m.data.voucher.description}</div>
                        <div className="divide-y divide-border/60 border border-border/60 rounded-md overflow-hidden">
                          {m.data.voucher.details?.map((line: any, lIdx: number) => (
                            <div key={lIdx} className="px-2 py-1.5 flex justify-between items-center text-[11px]">
                              <div>
                                <span className="font-mono font-semibold">[{line.account_code}]</span>{" "}
                                <span className="text-muted-foreground">{line.account_name}</span>
                              </div>
                              <div className="font-mono font-medium">
                                {line.debit > 0 ? (
                                  <span className="text-emerald-600 font-semibold">Dr {line.debit.toLocaleString()}</span>
                                ) : (
                                  <span className="text-blue-600 font-semibold">Cr {line.credit.toLocaleString()}</span>
                                )}
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                      <div className="flex items-center justify-between pt-1 text-[11px]">
                        <span className="text-muted-foreground">Double-entry status:</span>
                        <span className="inline-flex items-center gap-1 font-semibold text-emerald-600">
                          <CheckCircle2 className="w-3.5 h-3.5" /> Balanced
                        </span>
                      </div>
                    </div>
                  )}

                  {/* 5. Voucher Success Card */}
                  {m.cardType === "voucher_success" && m.data && (
                    <div className="mt-3 p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 rounded-xl text-xs space-y-1.5">
                      <div className="flex items-center gap-1.5 font-semibold text-emerald-700 dark:text-emerald-300">
                        <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                        <span>Voucher Committed to General Ledger</span>
                      </div>
                      <div className="flex justify-between items-center text-[11px] pt-1 border-t border-emerald-200/60 dark:border-emerald-800/40">
                        <span className="text-muted-foreground">Reference:</span>
                        <span className="font-mono font-semibold text-foreground">{m.data.reference}</span>
                      </div>
                      {m.data.entry_id && (
                        <div className="flex justify-between items-center text-[11px]">
                          <span className="text-muted-foreground">Journal Entry ID:</span>
                          <span className="font-mono text-muted-foreground">#{m.data.entry_id}</span>
                        </div>
                      )}
                    </div>
                  )}

                  {/* 6. Financial Report Summary Card */}
                  {m.cardType === "financial_report" && m.data && (
                    <div className="mt-2.5 p-3 bg-background border border-border rounded-xl text-foreground text-xs space-y-2">
                      <div className="flex justify-between items-center font-semibold text-[11px] text-muted-foreground uppercase">
                        <span>Report Totals</span>
                        <span className="text-emerald-600 flex items-center gap-1">
                          <CheckCircle2 className="w-3 h-3" /> Mathematically Valid
                        </span>
                      </div>
                      <div className="grid grid-cols-2 gap-2 text-center">
                        <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40">
                          <div className="text-[10px] text-muted-foreground uppercase">Total Debits</div>
                          <div className="text-sm font-bold font-mono text-emerald-700 dark:text-emerald-300">
                            PKR {(m.data.total_debit || 0).toLocaleString()}
                          </div>
                        </div>
                        <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/40">
                          <div className="text-[10px] text-muted-foreground uppercase">Total Credits</div>
                          <div className="text-sm font-bold font-mono text-blue-700 dark:text-blue-300">
                            PKR {(m.data.total_credit || 0).toLocaleString()}
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* 7. Account List Card */}
                  {m.cardType === "account_list" && m.data?.accounts && (
                    <div className="mt-2.5 p-2 bg-background border border-border rounded-xl text-foreground text-xs space-y-1 max-h-48 overflow-y-auto">
                      {m.data.accounts.map((acc: any, aIdx: number) => (
                        <div
                          key={aIdx}
                          className="p-1.5 rounded-md hover:bg-muted flex items-center justify-between text-[11px]"
                        >
                          <div>
                            <span className="font-mono font-semibold">[{acc.code}]</span> {acc.name}
                          </div>
                          <div>
                            {acc.category ? (
                              <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 font-medium">
                                Category Folder
                              </span>
                            ) : (
                              <span className="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-medium">
                                Posting Leaf
                              </span>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Actions / Follow-up buttons */}
                  {m.actions && m.actions.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                      {m.actions.map((act, actIdx) => (
                        <button
                          key={actIdx}
                          onClick={() => handleActionClick(act)}
                          disabled={loading}
                          className={`px-3 py-1.5 font-semibold rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs ${
                            act.variant === "outline"
                              ? "bg-background hover:bg-muted border border-border text-foreground hover:text-primary"
                              : "bg-primary text-primary-foreground hover:opacity-95"
                          }`}
                        >
                          <span>{act.label}</span>
                          <ChevronRight className="w-3.5 h-3.5" />
                        </button>
                      ))}
                    </div>
                  )}
                </div>
                <span className="text-[10px] text-muted-foreground mt-1 px-1">{m.timestamp}</span>
              </div>
            ))}

            {loading && (
              <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <Loader2 className="w-4 h-4 animate-spin text-primary" />
                <span>Taliya is searching ledger & compiling brief...</span>
              </div>
            )}
          </div>

          {/* Input Box */}
          <div className="p-3 bg-background border-t border-border">
            <form
              onSubmit={(e) => {
                e.preventDefault()
                sendMessage(input)
              }}
              className="flex items-center gap-2"
            >
              <input
                type="text"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder="Ask Taliya (e.g. 'Tell me about voucher OB-2026-001', 'Meezan Bank')..."
                className="flex-1 text-xs px-3.5 py-2.5 bg-muted/60 border border-border rounded-xl focus:outline-none focus:ring-1 focus:ring-primary focus:bg-background transition-all"
                disabled={loading}
              />
              <button
                type="submit"
                disabled={loading || !input.trim()}
                className="p-2.5 bg-primary text-primary-foreground rounded-xl hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
                title="Send"
              >
                <Send className="w-4 h-4" />
              </button>
            </form>
          </div>
        </div>
      )}
    </>
  )
}
