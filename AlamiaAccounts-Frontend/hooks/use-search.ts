import { useQuery } from '@tanstack/react-query'
import { searchApi } from '@/lib/api'

export function useSearch(query: string, enabled = true) {
    return useQuery({
        queryKey: ['search', query],
        queryFn: async () => {
            const response = await searchApi.global(query)
            return response.data.data
        },
        enabled: enabled && query.length >= 2,
    })
}

export function useSearchVouchers(query: string, enabled = true) {
    return useQuery({
        queryKey: ['search-vouchers', query],
        queryFn: async () => {
            const response = await searchApi.vouchers(query)
            return response.data.data
        },
        enabled: enabled && query.length >= 2,
    })
}

export function useSearchAccounts(query: string, enabled = true) {
    return useQuery({
        queryKey: ['search-accounts', query],
        queryFn: async () => {
            const response = await searchApi.accounts(query)
            return response.data.data
        },
        enabled: enabled && query.length >= 2,
    })
}
