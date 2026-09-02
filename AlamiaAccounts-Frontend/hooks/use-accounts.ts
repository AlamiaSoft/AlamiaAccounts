import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { accountApi } from '@/lib/api'

function getCompanyCode(): string {
    return typeof window !== 'undefined' ? localStorage.getItem('current_company_code') || 'MAIN' : 'MAIN'
}

export function useAccounts() {
    const queryClient = useQueryClient()
    const company = getCompanyCode()

    const { data: accounts, isLoading } = useQuery({
        queryKey: ['accounts', company],
        queryFn: async () => {
            const response = await accountApi.getAll()
            return response.data.data || []
        },
    })

    const createAccount = useMutation({
        mutationFn: (data: any) => accountApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
        },
    })

    const updateAccount = useMutation({
        mutationFn: ({ code, data }: { code: string; data: any }) =>
            accountApi.update(code, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
        },
    })

    const deleteAccount = useMutation({
        mutationFn: (code: string) => accountApi.delete(code),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
        },
    })

    return {
        accounts: accounts || [],
        isLoading,
        createAccount,
        updateAccount,
        deleteAccount,
    }
}

export function useAccount(code: string) {
    const company = getCompanyCode()
    return useQuery({
        queryKey: ['account', company, code],
        queryFn: async () => {
            const response = await accountApi.getOne(code)
            return response.data.data
        },
        enabled: !!code,
    })
}
