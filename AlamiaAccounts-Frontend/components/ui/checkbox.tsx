'use client'

import * as React from 'react'
import { CheckIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'checked' | 'onChange'> {
  checked?: boolean
  onCheckedChange?: (checked: boolean) => void
  defaultChecked?: boolean
}

const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, checked, onCheckedChange, defaultChecked, ...props }, ref) => {
    const isControlled = typeof checked !== 'undefined'

    return (
      <label className="relative inline-flex items-center cursor-pointer select-none">
        <input
          type="checkbox"
          ref={ref}
          className="sr-only peer"
          checked={isControlled ? checked : undefined}
          defaultChecked={!isControlled ? defaultChecked : undefined}
          onChange={(e) => onCheckedChange?.(e.target.checked)}
          {...props}
        />
        <div
          data-slot="checkbox"
          className={cn(
            'peer border-input bg-background dark:bg-input/30 peer-checked:bg-primary peer-checked:text-primary-foreground peer-checked:border-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 size-4 shrink-0 rounded-[4px] border shadow-xs transition-colors flex items-center justify-center peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
            className,
          )}
        >
          <CheckIcon
            className={cn(
              "size-3.5 transition-transform",
              checked ? "opacity-100 scale-100 text-primary-foreground" : "opacity-0 scale-0"
            )}
          />
        </div>
      </label>
    )
  }
)
Checkbox.displayName = 'Checkbox'

export { Checkbox }
