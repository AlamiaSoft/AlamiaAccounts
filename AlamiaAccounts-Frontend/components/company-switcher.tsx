"use client"

import { useState } from "react"
import { Building2, Check, ChevronsUpDown, Plus } from "lucide-react"
import { Button } from "@/components/ui/button"
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"
import { useCompanies } from "@/hooks/use-companies"

interface CompanySwitcherProps {
  onAddCompany?: () => void
}

export default function CompanySwitcher({ onAddCompany }: CompanySwitcherProps) {
  const [open, setOpen] = useState(false)
  const { companies, currentCompany: apiCurrentCompany, switchCompany, isLoading } = useCompanies()
  const currentCompany = apiCurrentCompany || (companies && companies.length > 0 ? companies[0] : null)

  const handleCompanyChange = (companyCode: string) => {
    switchCompany.mutate(companyCode)
    setOpen(false)
  }

  if (isLoading && !currentCompany) {
    return (
      <Button
        variant="outline"
        className="w-full justify-between bg-background/50 border-sidebar-border"
        disabled
      >
        <div className="flex items-center gap-2">
          <Building2 className="w-4 h-4" />
          <span className="text-sm">Loading...</span>
        </div>
      </Button>
    )
  }

  if (!currentCompany) {
    return null
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className="w-full justify-between bg-background/50 border-sidebar-border hover:bg-accent"
        >
          <div className="flex items-center gap-2 overflow-hidden min-w-0">
            <Building2 className="w-4 h-4 shrink-0" />
            <div className="text-left overflow-hidden min-w-0 flex-1">
              <p className="text-sm font-medium truncate">{currentCompany.name}</p>
              <p className="text-xs text-muted-foreground truncate">{currentCompany.code}</p>
            </div>
          </div>
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[280px] p-0" align="start">
        <Command>
          <CommandInput placeholder="Search companies..." />
          <CommandList>
            <CommandEmpty>No company found.</CommandEmpty>
            <CommandGroup heading="Companies">
              {companies.map((company: any) => (
                <CommandItem
                  key={company.code}
                  onSelect={() => handleCompanyChange(company.code)}
                  className="cursor-pointer"
                >
                  <div className="flex items-center gap-2 flex-1 min-w-0">
                    <Building2 className="w-4 h-4 shrink-0" />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium truncate">{company.name}</p>
                      <p className="text-xs text-muted-foreground truncate">{company.code}</p>
                    </div>
                  </div>
                  <Check
                    className={cn(
                      "ml-auto h-4 w-4 shrink-0",
                      currentCompany.code === company.code ? "opacity-100" : "opacity-0",
                    )}
                  />
                </CommandItem>
              ))}
            </CommandGroup>
            {onAddCompany && (
              <>
                <CommandSeparator />
                <CommandGroup>
                  <CommandItem
                    onSelect={() => {
                      onAddCompany()
                      setOpen(false)
                    }}
                    className="cursor-pointer"
                  >
                    <Plus className="mr-2 h-4 w-4" />
                    Add Company
                  </CommandItem>
                </CommandGroup>
              </>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
