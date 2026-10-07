import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { salesApi } from '@/lib/api'

function getCompanyCode(): string {
    return typeof window !== 'undefined' ? localStorage.getItem('current_company_code') || 'MAIN' : 'MAIN'
}

export function useSales(params?: any) {
    const queryClient = useQueryClient()
    const company = getCompanyCode()

    const { data: salesResponse, isLoading, refetch } = useQuery({
        queryKey: ['sales', company, params],
        queryFn: async () => {
            const response = await salesApi.getAll({
                company_code: company,
                scope: 'all',
                ...params,
            })
            return response.data
        },
    })

    const approveSale = useMutation({
        mutationFn: ({ id, approverName }: { id: number | string; approverName?: string }) =>
            salesApi.approve(id, { approver_name: approverName }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['sales'] })
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
            queryClient.invalidateQueries({ queryKey: ['report'] })
            queryClient.invalidateQueries({ queryKey: ['reports'] })
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
        },
    })

    return {
        sales: salesResponse?.data || [],
        pagination: salesResponse?.pagination,
        isLoading,
        refetch,
        approveSale,
    }
}

export function useShiftReconciliation(params?: { date?: string; agent_id?: string; actual_cash?: number }) {
    const company = getCompanyCode()

    return useQuery({
        queryKey: ['shift-reconciliation', company, params],
        queryFn: async () => {
            const response = await salesApi.reconcileShift({
                company_code: company,
                ...params,
            })
            return response.data?.data
        },
    })
}
