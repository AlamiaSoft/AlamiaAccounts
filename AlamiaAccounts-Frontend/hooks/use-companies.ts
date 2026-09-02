import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { companyApi } from '@/lib/api'

export function useCompanies() {
    const queryClient = useQueryClient()

    const { data: companies, isLoading: loadingCompanies } = useQuery({
        queryKey: ['companies'],
        queryFn: async () => {
            const response = await companyApi.getAll()
            return response.data.data || []
        },
    })

    const { data: currentCompany, isLoading: loadingCurrent } = useQuery({
        queryKey: ['current-company'],
        queryFn: async () => {
            const response = await companyApi.getCurrent()
            return response.data.data
        },
    })

    const switchCompany = useMutation({
        mutationFn: (code: string) => companyApi.switch(code),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['current-company'] })
            // Invalidate all data queries since company changed
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
            queryClient.invalidateQueries({ queryKey: ['vouchers'] })
            queryClient.invalidateQueries({ queryKey: ['reports'] })
        },
    })

    const createCompany = useMutation({
        mutationFn: (data: any) => companyApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['companies'] })
        },
    })

    const updateCompany = useMutation({
        mutationFn: ({ code, data }: { code: string; data: any }) =>
            companyApi.update(code, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['companies'] })
            queryClient.invalidateQueries({ queryKey: ['current-company'] })
        },
    })

    const deleteCompany = useMutation({
        mutationFn: (code: string) => companyApi.delete(code),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['companies'] })
        },
    })

    return {
        companies: companies || [],
        currentCompany,
        isLoading: loadingCompanies || loadingCurrent,
        switchCompany,
        createCompany,
        updateCompany,
        deleteCompany,
    }
}
