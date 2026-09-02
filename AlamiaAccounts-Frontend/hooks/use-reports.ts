import { useQuery } from '@tanstack/react-query'
import { reportApi } from '@/lib/api'

export function useTrialBalance(asOfDate: string, currency: string = 'INR') {
    return useQuery({
        queryKey: ['report', 'trial-balance', asOfDate, currency],
        queryFn: async () => {
            const response = await reportApi.trialBalance(asOfDate, currency)
            return response.data.data
        },
        enabled: !!asOfDate,
    })
}

export function useProfitLoss(fromDate: string, toDate: string, currency: string = 'INR') {
    return useQuery({
        queryKey: ['report', 'profit-loss', fromDate, toDate, currency],
        queryFn: async () => {
            const response = await reportApi.profitLoss(fromDate, toDate, currency)
            return response.data.data
        },
        enabled: !!fromDate && !!toDate,
    })
}

export function useBalanceSheet(asOfDate: string, currency: string = 'INR') {
    return useQuery({
        queryKey: ['report', 'balance-sheet', asOfDate, currency],
        queryFn: async () => {
            const response = await reportApi.balanceSheet(asOfDate, currency)
            return response.data.data
        },
        enabled: !!asOfDate,
    })
}

export function useLedger(accountCode: string, fromDate: string, toDate: string, currency: string = 'INR') {
    return useQuery({
        queryKey: ['report', 'ledger', accountCode, fromDate, toDate, currency],
        queryFn: async () => {
            const response = await reportApi.ledger(accountCode, fromDate, toDate, currency)
            return response.data.data
        },
        enabled: !!accountCode && !!fromDate && !!toDate,
    })
}
