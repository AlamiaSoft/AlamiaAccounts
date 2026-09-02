import { useMutation, useQuery } from '@tanstack/react-query'
import { printApi } from '@/lib/api'

export function usePrintVoucher() {
    return useMutation({
        mutationFn: async (voucherId: string) => {
            const response = await printApi.voucher(voucherId)
            // Create blob URL and download
            const blob = new Blob([response.data], { type: 'application/pdf' })
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = `voucher-${voucherId}.pdf`
            link.click()
            window.URL.revokeObjectURL(url)
        },
    })
}

export function usePrintReport() {
    return useMutation({
        mutationFn: async ({ type, data }: { type: string; data: any }) => {
            const response = await printApi.report(type, data)
            // Create blob URL and download
            const blob = new Blob([response.data], { type: 'application/pdf' })
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = `${type}-report.pdf`
            link.click()
            window.URL.revokeObjectURL(url)
        },
    })
}

export function usePrintTemplate(templateType: string) {
    return useQuery({
        queryKey: ['print-template', templateType],
        queryFn: async () => {
            const response = await printApi.getTemplate(templateType)
            return response.data.data
        },
        enabled: !!templateType,
    })
}

export function useSavePrintTemplate() {
    return useMutation({
        mutationFn: (data: any) => printApi.saveTemplate(data),
    })
}

export function useUploadLogo() {
    return useMutation({
        mutationFn: ({ file, companyCode }: { file: File; companyCode?: string }) =>
            printApi.uploadLogo(file, companyCode),
    })
}
