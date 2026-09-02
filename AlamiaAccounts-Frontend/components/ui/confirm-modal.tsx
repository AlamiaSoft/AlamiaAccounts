import React, { useEffect, useRef, useState } from "react"
import { createPortal } from "react-dom"
import { AlertTriangle, Trash2, HelpCircle, Scale, Loader2, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

export interface ConfirmModalProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  description: string
  confirmText?: string
  cancelText?: string
  variant?: "danger" | "warning" | "info"
  isLoading?: boolean
  details?: React.ReactNode
}

export default function ConfirmModal({
  isOpen,
  onClose,
  onConfirm,
  title,
  description,
  confirmText,
  cancelText = "Cancel",
  variant = "warning",
  isLoading = false,
  details,
}: ConfirmModalProps) {
  const modalRef = useRef<HTMLDivElement>(null)
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
  }, [])

  // Close on Escape key
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (!isOpen) return
      if (e.key === "Escape" && !isLoading) {
        onClose()
      } else if (e.key === "Enter" && (e.metaKey || e.ctrlKey) && !isLoading) {
        onConfirm()
      }
    }
    window.addEventListener("keydown", handleKeyDown)
    return () => window.removeEventListener("keydown", handleKeyDown)
  }, [isOpen, isLoading, onClose, onConfirm])

  if (!isOpen || !mounted) return null

  const getVariantStyles = () => {
    switch (variant) {
      case "danger":
        return {
          icon: <Trash2 className="w-6 h-6 text-destructive" />,
          iconBg: "bg-destructive/15 text-destructive",
          confirmBtn: "bg-destructive hover:bg-destructive/90 text-destructive-foreground",
          border: "border-destructive/30",
          defaultConfirmText: "Delete",
        }
      case "warning":
        return {
          icon: <AlertTriangle className="w-6 h-6 text-amber-600 dark:text-amber-400" />,
          iconBg: "bg-amber-500/15 text-amber-600 dark:text-amber-400",
          confirmBtn: "bg-amber-600 hover:bg-amber-700 text-white",
          border: "border-amber-500/30",
          defaultConfirmText: "Confirm & Proceed",
        }
      case "info":
      default:
        return {
          icon: <Scale className="w-6 h-6 text-primary" />,
          iconBg: "bg-primary/15 text-primary",
          confirmBtn: "bg-primary hover:bg-primary/90 text-primary-foreground",
          border: "border-border",
          defaultConfirmText: "Confirm",
        }
    }
  }

  const styles = getVariantStyles()

  return createPortal(
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/70 backdrop-blur-xs transition-opacity animate-in fade-in-0 duration-200"
        onClick={() => !isLoading && onClose()}
      />

      {/* Modal Box */}
      <div
        ref={modalRef}
        className={cn(
          "relative z-[110] w-full max-w-md bg-card text-card-foreground border rounded-xl shadow-2xl overflow-hidden p-6 animate-in fade-in-0 zoom-in-95 duration-200",
          styles.border
        )}
      >
        {/* Close Icon */}
        <button
          type="button"
          onClick={onClose}
          disabled={isLoading}
          className="absolute top-4 right-4 p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors"
        >
          <X className="w-4 h-4" />
        </button>

        {/* Header with Icon */}
        <div className="flex items-start gap-4">
          <div className={cn("p-3 rounded-full shrink-0", styles.iconBg)}>
            {styles.icon}
          </div>
          <div className="space-y-1 pr-4">
            <h3 className="text-lg font-bold text-foreground leading-snug">{title}</h3>
            <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>
          </div>
        </div>

        {/* Optional Context/Impact Details */}
        {details && <div className="mt-4">{details}</div>}

        {/* Actions Footer */}
        <div className="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-border">
          <Button
            type="button"
            variant="outline"
            onClick={onClose}
            disabled={isLoading}
            className="text-sm"
          >
            {cancelText}
          </Button>
          <Button
            type="button"
            onClick={onConfirm}
            disabled={isLoading}
            className={cn("text-sm font-semibold shadow-xs", styles.confirmBtn)}
          >
            {isLoading && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
            {confirmText || styles.defaultConfirmText}
          </Button>
        </div>
      </div>
    </div>,
    document.body
  )
}