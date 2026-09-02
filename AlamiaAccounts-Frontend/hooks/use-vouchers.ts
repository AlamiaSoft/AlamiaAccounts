import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { voucherApi } from '@/lib/api'

export function useVouchers(params?: any) {
    const queryClient = useQueryClient()

    const { data: vouchers, isLoading } = useQuery({
        queryKey: ['vouchers', params],
        queryFn: async () => {
            const response = await voucherApi.getAll(params)
            return response.data.data || []
        },
    })

    const createVoucher = useMutation({
        mutationFn: (data: any) => voucherApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
        },
    })

    const updateVoucher = useMutation({
        mutationFn: ({ reference, data }: { reference: string; data: any }) =>
            voucherApi.update(reference, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
        },
    })

    const deleteVoucher = useMutation({
        mutationFn: (reference: string) => voucherApi.delete(reference),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
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
    return useQuery({
        queryKey: ['voucher', reference],
        queryFn: async () => {
            const response = await voucherApi.getOne(reference)
            return response.data.data
        },
        enabled: !!reference,
    })
}
