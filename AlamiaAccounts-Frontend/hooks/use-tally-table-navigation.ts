import { useState, useEffect, useCallback, useRef } from "react"

interface UseTallyTableNavigationOptions {
  itemCount: number
  onSelect?: (index: number) => void
  onEscape?: () => void
  initialIndex?: number
  enabled?: boolean
  autoScroll?: boolean
}

export function useTallyTableNavigation({
  itemCount,
  onSelect,
  onEscape,
  initialIndex = 0,
  enabled = true,
  autoScroll = true,
}: UseTallyTableNavigationOptions) {
  const [selectedIndex, setSelectedIndex] = useState<number>(() => {
    if (itemCount === 0) return -1
    return Math.min(Math.max(0, initialIndex), itemCount - 1)
  })

  const rowRefs = useRef<Map<number, HTMLElement>>(new Map())

  // Keep selected index within valid bounds when itemCount changes
  useEffect(() => {
    if (itemCount === 0) {
      setSelectedIndex(-1)
    } else {
      setSelectedIndex((prev) => {
        if (prev < 0) return 0
        if (prev >= itemCount) return itemCount - 1
        return prev
      })
    }
  }, [itemCount])

  // Scroll focused row into view
  useEffect(() => {
    if (!autoScroll || selectedIndex < 0) return
    const el = rowRefs.current.get(selectedIndex)
    if (el) {
      el.scrollIntoView({ block: "nearest", behavior: "smooth" })
    }
  }, [selectedIndex, autoScroll])

  const registerRowRef = useCallback((index: number, el: HTMLElement | null) => {
    if (el) {
      rowRefs.current.set(index, el)
    } else {
      rowRefs.current.delete(index)
    }
  }, [])

  useEffect(() => {
    if (!enabled) return

    const handleKeyDown = (e: KeyboardEvent) => {
      // Ignore key events if the user is typing in an editable field
      const target = e.target as HTMLElement | null
      const isInput =
        target &&
        (target.tagName === "INPUT" ||
          target.tagName === "TEXTAREA" ||
          target.tagName === "SELECT" ||
          target.isContentEditable)

      if (isInput) {
        if (e.key === "Escape" && onEscape) {
          onEscape()
        }
        return
      }

      if (e.key === "ArrowDown") {
        e.preventDefault()
        setSelectedIndex((prev) => (prev + 1 < itemCount ? prev + 1 : prev))
      } else if (e.key === "ArrowUp") {
        e.preventDefault()
        setSelectedIndex((prev) => (prev - 1 >= 0 ? prev - 1 : 0))
      } else if (e.key === "PageDown") {
        e.preventDefault()
        setSelectedIndex((prev) => Math.min(itemCount - 1, prev + 10))
      } else if (e.key === "PageUp") {
        e.preventDefault()
        setSelectedIndex((prev) => Math.max(0, prev - 10))
      } else if (e.key === "Home") {
        e.preventDefault()
        setSelectedIndex(0)
      } else if (e.key === "End") {
        e.preventDefault()
        setSelectedIndex(Math.max(0, itemCount - 1))
      } else if (e.key === "Enter") {
        if (selectedIndex >= 0 && selectedIndex < itemCount && onSelect) {
          e.preventDefault()
          onSelect(selectedIndex)
        }
      } else if (e.key === "Escape") {
        if (onEscape) {
          e.preventDefault()
          onEscape()
        }
      }
    }

    window.addEventListener("keydown", handleKeyDown)
    return () => {
      window.removeEventListener("keydown", handleKeyDown)
    }
  }, [enabled, itemCount, selectedIndex, onSelect, onEscape])

  const getRowProps = useCallback(
    (index: number, customClass = "") => {
      const isSelected = selectedIndex === index
      return {
        ref: (el: HTMLElement | null) => registerRowRef(index, el),
        tabIndex: isSelected ? 0 : -1,
        onClick: () => {
          setSelectedIndex(index)
        },
        onDoubleClick: () => {
          setSelectedIndex(index)
          if (onSelect) onSelect(index)
        },
        "data-selected": isSelected,
        className: `cursor-pointer transition-colors duration-150 select-none ${
          isSelected
            ? "bg-primary/10 dark:bg-primary/20 font-medium text-foreground ring-1 ring-inset ring-primary/40"
            : "hover:bg-muted/50"
        } ${customClass}`,
      }
    },
    [selectedIndex, onSelect, registerRowRef]
  )

  return {
    selectedIndex,
    setSelectedIndex,
    getRowProps,
    registerRowRef,
  }
}
