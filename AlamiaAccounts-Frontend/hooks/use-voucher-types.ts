import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { voucherTypeApi } from '@/lib/api'

export function useVoucherTypes() {
    const queryClient = useQueryClient()

    const { data: voucherTypes, isLoading } = useQuery({
        queryKey: ['voucher-types'],
        queryFn: async () => {
            const response = await voucherTypeApi.getAll()
            return response.data.data || []
        },
    })

    const createVoucherType = useMutation({
        mutationFn: (data: any) => voucherTypeApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['voucher-types'] })
        },
    })

    const updateVoucherType = useMutation({
        mutationFn: ({ id, data }: { id: number; data: any }) =>
            voucherTypeApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['voucher-types'] })
        },
    })

    const deleteVoucherType = useMutation({
        mutationFn: (id: number) => voucherTypeApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['voucher-types'] })
        },
    })

    const validateVoucherData = useMutation({
        mutationFn: ({ id, data }: { id: number; data: any }) =>
            voucherTypeApi.validate(id, data),
    })

    return {
        voucherTypes: voucherTypes || [],
        isLoading,
        createVoucherType,
        updateVoucherType,
        deleteVoucherType,
        validateVoucherData,
    }
}
