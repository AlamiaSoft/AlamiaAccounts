"use client"

import { useState, useEffect, useRef } from "react"
import { Search, FileText, Wallet, BookOpen, Users, X, History, Trash2, Clock, CornerDownLeft, Sparkles } from "lucide-react"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"
import { useSearch } from "@/hooks/use-search"
import { useSearchHistory, type RecentSearchItem } from "@/hooks/use-search-history"

export interface SearchResult {
  id: string
  type: "voucher" | "account" | "ledger" | "user"
  title: string
  subtitle?: string
  rawItem?: any
}

interface GlobalSearchProps {
  currentContext?: "vouchers" | "accounts" | "ledgers" | "users" | "reports" | "dashboard"
  onResultClick?: (result: SearchResult) => void
}

function formatRelativeTime(timestamp: number): string {
  const diff = Date.now() - timestamp
  const seconds = Math.floor(diff / 1000)
  if (seconds < 60) return "Just now"
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days === 1) return "Yesterday"
  return `${days}d ago`
}

export default function GlobalSearch({ currentContext, onResultClick }: GlobalSearchProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState("")
  const [activeIndex, setActiveIndex] = useState(0)
  const searchRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  // Use the search history hook
  const { history, addSearchItem, removeSearchItem, clearHistory } = useSearchHistory()

  // Use the live search API hook
  const { data: searchData, isLoading } = useSearch(searchQuery, searchQuery.length >= 2)

  // Transform live API data to SearchResult format
  const liveResults: SearchResult[] = searchData ? [
    ...(searchData.vouchers || []).map((v: any) => ({
      id: String(v.reference || v.number || v.id || v.entry_id),
      type: "voucher" as const,
      title: `Voucher ${v.reference || v.number || v.id}`,
      subtitle: `${v.type ? `${v.type} • ` : ""}${v.description || ""}${v.date ? ` • ${v.date}` : ""}`,
      rawItem: v,
    })),
    ...(searchData.accounts || []).map((a: any) => ({
      id: a.code || String(a.id || a.account_uuid),
      type: "account" as const,
      title: a.name,
      subtitle: `Code: ${a.code}${a.type ? ` • ${a.type}` : ""}`,
      rawItem: a,
    })),
    ...(searchData.ledger_entries || []).map((l: any) => ({
      id: String(l.account_code || l.id),
      type: "ledger" as const,
      title: l.account_name || `Account ${l.account_code}`,
      subtitle: `${l.voucher_reference ? `Ref: ${l.voucher_reference} • ` : ""}${l.description || ""}`,
      rawItem: l,
    })),
    ...(searchData.users || []).map((u: any) => ({
      id: String(u.id),
      type: "user" as const,
      title: u.name,
      subtitle: u.email,
      rawItem: u,
    })),
  ] : []

  // Items currently active for keyboard navigation
  const currentNavItems = searchQuery.length >= 2 ? liveResults : history

  // Keyboard shortcuts & navigation
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Cmd+K or Ctrl+K to open search
      if ((e.metaKey || e.ctrlKey) && e.key === "k") {
        e.preventDefault()
        setIsOpen(true)
        setTimeout(() => inputRef.current?.focus(), 100)
      }

      // Escape to close
      if (e.key === "Escape") {
        setIsOpen(false)
        setSearchQuery("")
      }

      // Arrow navigation
      if (isOpen && currentNavItems.length > 0) {
        if (e.key === "ArrowDown") {
          e.preventDefault()
          setActiveIndex((prev) => (prev + 1) % currentNavItems.length)
        }
        if (e.key === "ArrowUp") {
          e.preventDefault()
          setActiveIndex((prev) => (prev - 1 + currentNavItems.length) % currentNavItems.length)
        }
        if (e.key === "Enter" && currentNavItems[activeIndex]) {
          e.preventDefault()
          handleResultClick(currentNavItems[activeIndex])
        }
      }
    }

    window.addEventListener("keydown", handleKeyDown)
    return () => window.removeEventListener("keydown", handleKeyDown)
  }, [isOpen, currentNavItems, activeIndex])

  // Click outside to close
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (searchRef.current && !searchRef.current.contains(e.target as Node)) {
        setIsOpen(false)
      }
    }

    if (isOpen) {
      document.addEventListener("mousedown", handleClickOutside)
      return () => document.removeEventListener("mousedown", handleClickOutside)
    }
  }, [isOpen])

  const handleResultClick = (result: SearchResult | RecentSearchItem) => {
    // Save to recent search history
    addSearchItem({
      id: result.id,
      type: result.type,
      title: result.title,
      subtitle: result.subtitle,
      rawItem: result.rawItem,
    })

    onResultClick?.(result)
    setIsOpen(false)
    setSearchQuery("")
  }

  const handleAskTaliya = (itemOrQuery: string | SearchResult | RecentSearchItem) => {
    setIsOpen(false)
    let prompt = ""
    let context: any = undefined

    if (typeof itemOrQuery === "string") {
      prompt = `Tell me about ${itemOrQuery}`
    } else if (itemOrQuery.type === "voucher") {
      prompt = `Tell me about voucher ${itemOrQuery.id}`
      context = { type: "voucher", id: itemOrQuery.id, raw: itemOrQuery.rawItem }
    } else if (itemOrQuery.type === "account") {
      prompt = `What is the balance and ledger for account ${itemOrQuery.id}?`
      context = { type: "account", code: itemOrQuery.id, raw: itemOrQuery.rawItem }
    } else if (itemOrQuery.type === "ledger") {
      prompt = `Show ledger activity for ${itemOrQuery.title} (${itemOrQuery.id})`
      context = { type: "ledger", code: itemOrQuery.id, raw: itemOrQuery.rawItem }
    } else if (itemOrQuery.type === "user") {
      prompt = `Tell me about user ${itemOrQuery.title}`
      context = { type: "user", id: itemOrQuery.id, raw: itemOrQuery.rawItem }
    } else {
      prompt = `Tell me about ${(itemOrQuery as any).title || searchQuery}`
    }

    if (typeof window !== "undefined") {
      window.dispatchEvent(
        new CustomEvent("copilot:open", {
          detail: { prompt, context },
        })
      )
    }
  }

  const getIcon = (type: string) => {
    switch (type) {
      case "voucher":
        return <FileText className="w-4 h-4" />
      case "account":
        return <Wallet className="w-4 h-4" />
      case "ledger":
        return <BookOpen className="w-4 h-4" />
      case "user":
        return <Users className="w-4 h-4" />
      default:
        return <Search className="w-4 h-4" />
    }
  }

  return (
    <div ref={searchRef} className="relative w-full max-w-md">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        <Input
          ref={inputRef}
          type="text"
          placeholder="Search vouchers, accounts... (⌘K)"
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value)
            setActiveIndex(0)
          }}
          onFocus={() => setIsOpen(true)}
          className="pl-9 pr-9 bg-background/80 focus:bg-background transition-colors"
        />
        {searchQuery && (
          <button
            type="button"
            onClick={() => {
              setSearchQuery("")
              setActiveIndex(0)
              inputRef.current?.focus()
            }}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5 rounded-sm"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>

      {/* Floating Dropdown Modal */}
      {isOpen && (
        <div className="absolute top-full mt-2 w-full bg-popover text-popover-foreground border border-border rounded-xl shadow-2xl max-h-[28rem] overflow-hidden z-[100] animate-in fade-in-0 zoom-in-95 duration-150">
          {searchQuery.length >= 2 ? (
            /* Live API Search Results */
            isLoading ? (
              <div className="p-6 text-center text-sm text-muted-foreground flex items-center justify-center gap-2">
                <span className="w-3.5 h-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                <span>Searching ledger...</span>
              </div>
            ) : (
              <div>
                {liveResults.length === 0 ? (
                  <div className="p-5 text-center text-sm text-muted-foreground">
                    <p>No results found for &ldquo;<span className="font-semibold text-foreground">{searchQuery}</span>&rdquo;</p>
                    <p className="text-xs text-muted-foreground/80 mt-1">Try searching by voucher reference, account code, or description.</p>
                  </div>
                ) : (
                  <div className="py-2 overflow-y-auto max-h-[20rem]">
                    <div className="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Matching Results ({liveResults.length})
                    </div>
                    {liveResults.map((result, index) => (
                      <div
                        key={`${result.type}-${result.id}-${index}`}
                        onClick={() => handleResultClick(result)}
                        onMouseEnter={() => setActiveIndex(index)}
                        className={cn(
                          "w-full px-3.5 py-2.5 text-left hover:bg-accent/80 transition-colors flex items-start gap-3 group border-b border-border/40 last:border-0 cursor-pointer",
                          index === activeIndex && "bg-accent"
                        )}
                      >
                        <div className="mt-0.5 p-1.5 rounded-md bg-muted text-muted-foreground group-hover:text-primary group-hover:bg-primary/10 transition-colors">
                          {getIcon(result.type)}
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">
                            {result.title}
                          </p>
                          {result.subtitle && (
                            <p className="text-xs text-muted-foreground truncate mt-0.5">
                              {result.subtitle}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center gap-1.5 shrink-0">
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation()
                              handleAskTaliya(result)
                            }}
                            className="px-2 py-1 rounded-md bg-amber-500/10 hover:bg-primary text-foreground hover:text-primary-foreground border border-amber-500/30 hover:border-primary transition-all flex items-center gap-1.5 text-[11px] font-medium shadow-2xs cursor-pointer"
                            title="Ask Taliya AI Copilot about this record"
                          >
                            <Sparkles className="w-3.5 h-3.5 text-amber-500 group-hover:text-amber-300 shrink-0" />
                            <span>Ask Taliya</span>
                          </button>
                          <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-muted/60 text-muted-foreground capitalize">
                            {result.type}
                          </span>
                          <CornerDownLeft className="w-3.5 h-3.5 text-muted-foreground opacity-40 group-hover:opacity-100 transition-opacity" />
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Persistent Ask Taliya Footer Banner */}
                <div className="p-2 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border-t border-border">
                  <button
                    type="button"
                    onClick={() => handleAskTaliya(searchQuery)}
                    className="w-full py-2 px-3 rounded-lg bg-background hover:bg-accent border border-primary/20 hover:border-primary/40 text-left flex items-center justify-between text-xs transition-all group shadow-2xs cursor-pointer"
                  >
                    <div className="flex items-center gap-2 truncate">
                      <div className="w-5 h-5 rounded-full bg-primary/15 text-primary flex items-center justify-center shrink-0">
                        <Sparkles className="w-3.5 h-3.5 text-amber-500 animate-pulse" />
                      </div>
                      <span className="text-foreground truncate text-xs">
                        Ask Taliya AI about &ldquo;<strong className="text-primary font-semibold">{searchQuery}</strong>&rdquo;
                      </span>
                    </div>
                    <span className="text-[10px] text-muted-foreground group-hover:text-primary font-medium shrink-0 ml-2">
                      Ask Copilot →
                    </span>
                  </button>
                </div>
              </div>
            )
          ) : (
            /* Recent Search History (When query is empty or < 2 characters) */
            <div className="py-2">
              <div className="px-3.5 py-2 flex items-center justify-between border-b border-border/50">
                <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  <History className="w-3.5 h-3.5 text-primary" />
                  <span>Recent Searches</span>
                </div>
                {history.length > 0 && (
                  <button
                    type="button"
                    onClick={clearHistory}
                    className="text-[11px] font-medium text-muted-foreground hover:text-destructive flex items-center gap-1 px-2 py-0.5 rounded hover:bg-destructive/10 transition-colors cursor-pointer"
                  >
                    <Trash2 className="w-3 h-3" />
                    <span>Clear all</span>
                  </button>
                )}
              </div>

              {history.length === 0 ? (
                <div className="p-6 text-center text-sm text-muted-foreground">
                  <Clock className="w-6 h-6 mx-auto mb-2 text-muted-foreground/60" />
                  <p className="font-medium text-foreground text-xs">No recent searches</p>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    Search vouchers (e.g. <span className="font-mono text-primary font-semibold">OB-2026-001</span>), accounts, or ledgers.
                  </p>
                </div>
              ) : (
                <div className="overflow-y-auto max-h-[22rem]">
                  {history.map((item, index) => (
                    <div
                      key={`recent-${item.type}-${item.id}-${index}`}
                      onClick={() => handleResultClick(item)}
                      onMouseEnter={() => setActiveIndex(index)}
                      className={cn(
                        "w-full px-3.5 py-2.5 text-left hover:bg-accent/80 transition-colors flex items-center justify-between gap-3 group cursor-pointer border-b border-border/30 last:border-0",
                        index === activeIndex && "bg-accent"
                      )}
                    >
                      <div className="flex items-start gap-3 min-w-0 flex-1">
                        <div className="mt-0.5 p-1.5 rounded-md bg-muted text-muted-foreground group-hover:text-primary group-hover:bg-primary/10 transition-colors">
                          {getIcon(item.type)}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">
                            {item.title}
                          </p>
                          {item.subtitle && (
                            <p className="text-xs text-muted-foreground truncate mt-0.5">
                              {item.subtitle}
                            </p>
                          )}
                        </div>
                      </div>

                      <div className="flex items-center gap-1.5 shrink-0">
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation()
                            handleAskTaliya(item)
                          }}
                          className="px-2 py-1 rounded-md bg-amber-500/10 hover:bg-primary text-foreground hover:text-primary-foreground border border-amber-500/30 hover:border-primary transition-all flex items-center gap-1.5 text-[11px] font-medium shadow-2xs cursor-pointer"
                          title="Ask Taliya AI about this recent search"
                        >
                          <Sparkles className="w-3.5 h-3.5 text-amber-500 group-hover:text-amber-300 shrink-0" />
                          <span>Ask Taliya</span>
                        </button>
                        <span className="text-[10px] text-muted-foreground hidden sm:inline-block">
                          {formatRelativeTime(item.timestamp)}
                        </span>
                        <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-muted/60 text-muted-foreground capitalize">
                          {item.type}
                        </span>
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation()
                            removeSearchItem(item.id, item.type)
                          }}
                          className="p-1 rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors opacity-70 hover:opacity-100 cursor-pointer"
                          title="Remove from history"
                        >
                          <X className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
