"use client"

import { useState, useEffect, useCallback } from "react"

export interface RecentSearchItem {
  id: string
  type: "voucher" | "account" | "ledger" | "user"
  title: string
  subtitle?: string
  timestamp: number
  rawItem?: any
}

function getCompanyCode(): string {
  return typeof window !== "undefined"
    ? localStorage.getItem("current_company_code") || "MAIN"
    : "MAIN"
}

export function useSearchHistory() {
  const [history, setHistory] = useState<RecentSearchItem[]>([])
  const companyCode = getCompanyCode()
  const storageKey = `alamia_recent_searches_${companyCode}`

  // Load history from localStorage
  const loadHistory = useCallback(() => {
    if (typeof window === "undefined") return
    try {
      const raw = localStorage.getItem(storageKey)
      if (raw) {
        const parsed = JSON.parse(raw)
        if (Array.isArray(parsed)) {
          setHistory(parsed)
          return
        }
      }
      setHistory([])
    } catch {
      setHistory([])
    }
  }, [storageKey])

  useEffect(() => {
    loadHistory()
  }, [loadHistory])

  // Add or bump an item to top of history
  const addSearchItem = useCallback(
    (item: Omit<RecentSearchItem, "timestamp">) => {
      if (typeof window === "undefined") return
      try {
        const currentRaw = localStorage.getItem(storageKey)
        const current: RecentSearchItem[] = currentRaw ? JSON.parse(currentRaw) : []

        // Remove existing occurrence if any
        const filtered = current.filter(
          (existing) => !(existing.id === item.id && existing.type === item.type)
        )

        // Insert at beginning with fresh timestamp
        const updated: RecentSearchItem[] = [
          {
            ...item,
            timestamp: Date.now(),
          },
          ...filtered,
        ].slice(0, 10) // Max 10 items

        localStorage.setItem(storageKey, JSON.stringify(updated))
        setHistory(updated)
      } catch (err) {
        console.error("Failed to save search history:", err)
      }
    },
    [storageKey]
  )

  // Remove a single item
  const removeSearchItem = useCallback(
    (id: string, type?: string) => {
      if (typeof window === "undefined") return
      try {
        const currentRaw = localStorage.getItem(storageKey)
        const current: RecentSearchItem[] = currentRaw ? JSON.parse(currentRaw) : []
        const updated = current.filter((item) => {
          if (type) {
            return !(item.id === id && item.type === type)
          }
          return item.id !== id
        })

        localStorage.setItem(storageKey, JSON.stringify(updated))
        setHistory(updated)
      } catch (err) {
        console.error("Failed to remove search item:", err)
      }
    },
    [storageKey]
  )

  // Clear all history for this company
  const clearHistory = useCallback(() => {
    if (typeof window === "undefined") return
    try {
      localStorage.removeItem(storageKey)
      setHistory([])
    } catch (err) {
      console.error("Failed to clear search history:", err)
    }
  }, [storageKey])

  return {
    history,
    addSearchItem,
    removeSearchItem,
    clearHistory,
    reloadHistory: loadHistory,
  }
}
