import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { accountApi } from '@/lib/api'

export function useAccounts() {
    const queryClient = useQueryClient()

    const { data: accounts, isLoading } = useQuery({
        queryKey: ['accounts'],
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
    return useQuery({
        queryKey: ['account', code],
        queryFn: async () => {
            const response = await accountApi.getOne(code)
            return response.data.data
        },
        enabled: !!code,
    })
}
