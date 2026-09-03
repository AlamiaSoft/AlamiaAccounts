"use client"

import React, { useState, useRef, useEffect, useMemo } from "react"
import { Check, ChevronsUpDown, Search, Folder, X, AlertCircle } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"

export interface AccountOption {
  code: string
  name: string
  category: boolean
  type?: string
  groupId?: string
}

interface AccountComboboxProps {
  accounts: AccountOption[]
  selectedCode: string
  selectedName: string
  onSelect: (account: { code: string; name: string }) => void
  disabled?: boolean
  placeholder?: string
  className?: string
}

export default function AccountCombobox({
  accounts,
  selectedCode,
  selectedName,
  onSelect,
  disabled = false,
  placeholder = "Search account by name or code...",
  className,
}: AccountComboboxProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState("")
  const [highlightedIndex, setHighlightedIndex] = useState(0)
  const containerRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const listRef = useRef<HTMLUListElement>(null)

  // Filter accounts based on query (by code or name)
  const filteredAccounts = useMemo(() => {
    if (!searchQuery.trim()) {
      return accounts
    }
    const q = searchQuery.toLowerCase().trim()
    return accounts.filter(
      (a) => a.name.toLowerCase().includes(q) || a.code.toLowerCase().includes(q)
    )
  }, [accounts, searchQuery])

  // Selectable (posting) accounts count
  const selectableAccounts = useMemo(() => {
    return filteredAccounts.filter((a) => !a.category)
  }, [filteredAccounts])

  // Close dropdown on click outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false)
      }
    }
    document.addEventListener("mousedown", handleClickOutside)
    return () => document.removeEventListener("mousedown", handleClickOutside)
  }, [])

  // Reset highlight when filtered accounts change
  useEffect(() => {
    setHighlightedIndex(0)
  }, [filteredAccounts])

  // Scroll highlighted item into view
  useEffect(() => {
    if (isOpen && listRef.current) {
      const activeEl = listRef.current.children[highlightedIndex] as HTMLElement
      if (activeEl) {
        activeEl.scrollIntoView({ block: "nearest" })
      }
    }
  }, [highlightedIndex, isOpen])

  const handleSelect = (account: AccountOption) => {
    if (account.category) return // Cannot post to category folder
    onSelect({ code: account.code, name: account.name })
    setIsOpen(false)
    setSearchQuery("")
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (disabled) return

    if (!isOpen) {
      if (e.key === "ArrowDown" || e.key === "Enter" || e.key === " ") {
        e.preventDefault()
        setIsOpen(true)
        setTimeout(() => inputRef.current?.focus(), 50)
      }
      return
    }

    if (e.key === "ArrowDown") {
      e.preventDefault()
      // find next selectable item
      let next = highlightedIndex + 1
      while (next < filteredAccounts.length && filteredAccounts[next]?.category) {
        next++
      }
      if (next < filteredAccounts.length) {
        setHighlightedIndex(next)
      }
    } else if (e.key === "ArrowUp") {
      e.preventDefault()
      // find prev selectable item
      let prev = highlightedIndex - 1
      while (prev >= 0 && filteredAccounts[prev]?.category) {
        prev--
      }
      if (prev >= 0) {
        setHighlightedIndex(prev)
      }
    } else if (e.key === "Enter") {
      e.preventDefault()
      const target = filteredAccounts[highlightedIndex]
      if (target && !target.category) {
        handleSelect(target)
      }
    } else if (e.key === "Escape") {
      e.preventDefault()
      setIsOpen(false)
    }
  }

  const getTypeBadgeClass = (type?: string) => {
    switch (type?.toLowerCase()) {
      case "asset":
        return "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
      case "liability":
        return "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
      case "income":
        return "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300"
      case "expense":
        return "bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300"
      case "capital":
        return "bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300"
      default:
        return "bg-muted text-muted-foreground"
    }
  }

  return (
    <div ref={containerRef} className={cn("relative w-full", isOpen ? "z-50" : "z-auto", className)}>
      {/* Trigger Button / Input */}
      <div
        role="button"
        tabIndex={disabled ? -1 : 0}
        onClick={() => {
          if (!disabled) {
            setIsOpen(!isOpen)
            if (!isOpen) {
              setTimeout(() => inputRef.current?.focus(), 50)
            }
          }
        }}
        onKeyDown={handleKeyDown}
        className={cn(
          "flex items-center justify-between w-full h-9 px-3 py-1.5 text-sm rounded-md border border-input bg-background shadow-xs transition-colors cursor-pointer",
          disabled && "cursor-not-allowed opacity-50 bg-muted",
          isOpen && "ring-2 ring-primary border-primary",
          !selectedName && "text-muted-foreground"
        )}
      >
        <span className="truncate font-medium text-foreground">
          {selectedName ? (
            <span className="flex items-center gap-2">
              <span className="font-semibold text-primary">[{selectedCode}]</span>
              <span>{selectedName}</span>
            </span>
          ) : (
            placeholder
          )}
        </span>
        <div className="flex items-center gap-1 shrink-0 ml-2">
          {selectedCode && !disabled && (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                onSelect({ code: "", name: "" })
              }}
              className="p-0.5 rounded-sm hover:bg-muted text-muted-foreground hover:text-foreground"
            >
              <X className="w-3 h-3" />
            </button>
          )}
          <ChevronsUpDown className="w-3.5 h-3.5 text-muted-foreground" />
        </div>
      </div>

      {/* Floating Searchable Dropdown */}
      {isOpen && (
        <div className="absolute left-0 top-full mt-1 w-full min-w-[340px] max-w-[480px] z-[100] bg-popover text-popover-foreground border border-border rounded-lg shadow-2xl overflow-hidden animate-in fade-in-0 zoom-in-95 duration-150">
          {/* Search Input */}
          <div className="flex items-center px-3 py-2 border-b border-border bg-muted/30">
            <Search className="w-4 h-4 text-muted-foreground mr-2 shrink-0" />
            <input
              ref={inputRef}
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Type name (e.g. Meezan) or code (1130)..."
              className="w-full bg-transparent text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
            />
            {searchQuery && (
              <button
                type="button"
                onClick={() => setSearchQuery("")}
                className="p-1 text-muted-foreground hover:text-foreground"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          {/* Account Options List */}
          <ul
            ref={listRef}
            className="max-h-60 overflow-y-auto p-1 text-sm divide-y divide-border/20"
          >
            {filteredAccounts.length === 0 ? (
              <li className="p-4 text-center text-xs text-muted-foreground">
                No accounts match "{searchQuery}"
              </li>
            ) : (
              filteredAccounts.map((account, index) => {
                const isSelected = account.code === selectedCode
                const isHighlighted = index === highlightedIndex
                const isCategory = account.category

                if (isCategory) {
                  return (
                    <li
                      key={account.code}
                      className="px-3 py-1.5 text-xs font-semibold text-muted-foreground bg-muted/40 flex items-center gap-1.5 select-none"
                    >
                      <Folder className="w-3.5 h-3.5 text-muted-foreground/70" />
                      <span>{account.code} - {account.name}</span>
                      <span className="text-[10px] font-normal italic ml-auto text-muted-foreground/60">
                        (Folder Header)
                      </span>
                    </li>
                  )
                }

                return (
                  <li
                    key={account.code}
                    onMouseEnter={() => setHighlightedIndex(index)}
                    onClick={() => handleSelect(account)}
                    className={cn(
                      "flex items-center justify-between px-3 py-2 rounded-md cursor-pointer transition-colors text-xs sm:text-sm",
                      isHighlighted ? "bg-accent text-accent-foreground" : "hover:bg-muted/60",
                      isSelected && "font-semibold bg-primary/10 text-primary"
                    )}
                  >
                    <div className="flex items-center gap-2 min-w-0 pr-2">
                      <span className="font-mono text-xs px-1.5 py-0.5 rounded bg-muted border border-border text-foreground shrink-0">
                        {account.code}
                      </span>
                      <span className="truncate text-foreground font-medium">
                        {account.name}
                      </span>
                    </div>

                    <div className="flex items-center gap-1.5 shrink-0">
                      {account.type && (
                        <span
                          className={cn(
                            "px-1.5 py-0.5 text-[10px] font-medium rounded-full",
                            getTypeBadgeClass(account.type)
                          )}
                        >
                          {account.type}
                        </span>
                      )}
                      {isSelected && <Check className="w-4 h-4 text-primary shrink-0" />}
                    </div>
                  </li>
                )
              })
            )}
          </ul>

          {/* Footer Info */}
          <div className="p-1.5 text-[11px] bg-muted/40 border-t border-border text-muted-foreground flex justify-between items-center px-3">
            <span>{selectableAccounts.length} posting accounts available</span>
            <span className="text-[10px]">↑↓ to navigate, Enter to select</span>
          </div>
        </div>
      )}
    </div>
  )
}