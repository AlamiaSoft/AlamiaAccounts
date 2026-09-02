"use client"

import { useState, useEffect, useRef } from "react"
import { Search, FileText, Wallet, BookOpen, Users, X } from "lucide-react"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"
import { useSearch } from "@/hooks/use-search"

interface SearchResult {
  id: string
  type: "voucher" | "account" | "ledger" | "user"
  title: string
  subtitle?: string
}

interface GlobalSearchProps {
  currentContext?: "vouchers" | "accounts" | "ledgers" | "users" | "reports" | "dashboard"
  onResultClick?: (result: SearchResult) => void
}

export default function GlobalSearch({ currentContext, onResultClick }: GlobalSearchProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState("")
  const [activeIndex, setActiveIndex] = useState(0)
  const searchRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  // Use the search hook
  const { data: searchData, isLoading } = useSearch(searchQuery, searchQuery.length >= 2)

  // Transform API data to SearchResult format
  const results: SearchResult[] = searchData ? [
    ...(searchData.vouchers || []).map((v: any) => ({
      id: v.entry_id || v.id,
      type: "voucher" as const,
      title: `Voucher ${v.reference}`,
      subtitle: v.description,
    })),
    ...(searchData.accounts || []).map((a: any) => ({
      id: a.account_uuid || a.id,
      type: "account" as const,
      title: a.name,
      subtitle: `${a.code}`,
    })),
    ...(searchData.ledger_entries || []).map((l: any) => ({
      id: l.id,
      type: "ledger" as const,
      title: l.account_name,
      subtitle: l.description,
    })),
    ...(searchData.users || []).map((u: any) => ({
      id: u.id,
      type: "user" as const,
      title: u.name,
      subtitle: u.email,
    })),
  ] : []

  // Keyboard shortcuts
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
      if (isOpen && results.length > 0) {
        if (e.key === "ArrowDown") {
          e.preventDefault()
          setActiveIndex((prev) => (prev + 1) % results.length)
        }
        if (e.key === "ArrowUp") {
          e.preventDefault()
          setActiveIndex((prev) => (prev - 1 + results.length) % results.length)
        }
        if (e.key === "Enter" && results[activeIndex]) {
          e.preventDefault()
          handleResultClick(results[activeIndex])
        }
      }
    }

    window.addEventListener("keydown", handleKeyDown)
    return () => window.removeEventListener("keydown", handleKeyDown)
  }, [isOpen, results, activeIndex])

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

  const handleResultClick = (result: SearchResult) => {
    onResultClick?.(result)
    setIsOpen(false)
    setSearchQuery("")
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
          placeholder="Search... (⌘K)"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          onFocus={() => setIsOpen(true)}
          className="pl-9 pr-9"
        />
        {searchQuery && (
          <button
            onClick={() => {
              setSearchQuery("")
              inputRef.current?.focus()
            }}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>

      {/* Results dropdown */}
      {isOpen && searchQuery.length >= 2 && (
        <div className="absolute top-full mt-2 w-full bg-popover border rounded-lg shadow-lg max-h-96 overflow-y-auto z-50">
          {isLoading ? (
            <div className="p-4 text-center text-sm text-muted-foreground">
              Searching...
            </div>
          ) : results.length === 0 ? (
            <div className="p-4 text-center text-sm text-muted-foreground">
              No results found for "{searchQuery}"
            </div>
          ) : (
            <div className="py-2">
              {results.map((result, index) => (
                <button
                  key={`${result.type}-${result.id}`}
                  onClick={() => handleResultClick(result)}
                  className={cn(
                    "w-full px-4 py-2 text-left hover:bg-accent transition-colors flex items-start gap-3",
                    index === activeIndex && "bg-accent"
                  )}
                >
                  <div className="mt-0.5 text-muted-foreground">
                    {getIcon(result.type)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{result.title}</p>
                    {result.subtitle && (
                      <p className="text-xs text-muted-foreground truncate">
                        {result.subtitle}
                      </p>
                    )}
                  </div>
                  <span className="text-xs text-muted-foreground capitalize shrink-0">
                    {result.type}
                  </span>
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
