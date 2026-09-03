import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { voucherApi } from '@/lib/api'

function getCompanyCode(): string {
    return typeof window !== 'undefined' ? localStorage.getItem('current_company_code') || 'MAIN' : 'MAIN'
}

export function useVouchers(params?: any) {
    const queryClient = useQueryClient()
    const company = getCompanyCode()

    const { data: vouchers, isLoading } = useQuery({
        queryKey: ['vouchers', company, params],
        queryFn: async () => {
            const response = await voucherApi.getAll(params)
            return response.data.data || []
        },
    })

    const createVoucher = useMutation({
        mutationFn: (data: any) => voucherApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
            queryClient.invalidateQueries({ queryKey: ['report'] })
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
        },
    })

    const updateVoucher = useMutation({
        mutationFn: ({ reference, data }: { reference: string; data: any }) =>
            voucherApi.update(reference, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
            queryClient.invalidateQueries({ queryKey: ['report'] })
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
        },
    })

    const deleteVoucher = useMutation({
        mutationFn: (reference: string) => voucherApi.delete(reference),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
            queryClient.invalidateQueries({ queryKey: ['report'] })
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
        },
    })

    return {
        vouchers: vouchers || [],
        isLoading,
        createVoucher,
        updateVoucher,
        deleteVoucher,
    }
}

export function useVoucher(reference: string) {
    const company = getCompanyCode()
    return useQuery({
        queryKey: ['voucher', company, reference],
        queryFn: async () => {
            const response = await voucherApi.getOne(reference)
            return response.data.data
        },
        enabled: !!reference,
    })
}
