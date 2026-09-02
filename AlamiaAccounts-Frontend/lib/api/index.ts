import apiClient from '../api-client'

// Authentication API
export const authApi = {
    login: (email: string, password: string) =>
        apiClient.post('/login', { email, password }),
    logout: () => apiClient.post('/logout'),
    me: () => apiClient.get('/me'),
}

// Company API
export const companyApi = {
    getAll: () => apiClient.get('/companies'),
    getCurrent: () => apiClient.get('/companies/current'),
    switch: (code: string) => apiClient.post(`/companies/${code}/switch`),
    create: (data: any) => apiClient.post('/companies', data),
    update: (code: string, data: any) => apiClient.put(`/companies/${code}`, data),
    delete: (code: string) => apiClient.delete(`/companies/${code}`),
}

// User API
export const userApi = {
    getAll: () => apiClient.get('/users'),
    getOne: (id: string | number) => apiClient.get(`/users/${id}`),
    create: (data: any) => apiClient.post('/users', data),
    update: (id: string | number, data: any) => apiClient.put(`/users/${id}`, data),
    delete: (id: string | number) => apiClient.delete(`/users/${id}`),
}

// Account API
export const accountApi = {
    getAll: () => apiClient.get('/accounts'),
    getOne: (code: string) => apiClient.get(`/accounts/${code}`),
    create: (data: any) => apiClient.post('/accounts', data),
    update: (code: string, data: any) => apiClient.put(`/accounts/${code}`, data),
    delete: (code: string) => apiClient.delete(`/accounts/${code}`),
    setOpeningBalance: (code: string, amount: number, date?: string) =>
        apiClient.post(`/accounts/${code}/opening-balance`, { opening_balance: amount, date }),
}

// Voucher API
export const voucherApi = {
    getAll: (params?: any) => apiClient.get('/vouchers', { params }),
    getOne: (reference: string) => apiClient.get(`/vouchers/${reference}`),
    create: (data: any) => apiClient.post('/vouchers', data),
    update: (reference: string, data: any) => apiClient.put(`/vouchers/${reference}`, data),
    delete: (reference: string) => apiClient.delete(`/vouchers/${reference}`),
    reverse: (reference: string, date?: string) => apiClient.post(`/vouchers/${reference}/reverse`, { date }),
}

// Custom Voucher Type API
export const voucherTypeApi = {
    getAll: () => apiClient.get('/voucher-types'),
    getOne: (id: number) => apiClient.get(`/voucher-types/${id}`),
    create: (data: any) => apiClient.post('/voucher-types', data),
    update: (id: number, data: any) => apiClient.put(`/voucher-types/${id}`, data),
    delete: (id: number) => apiClient.delete(`/voucher-types/${id}`),
    validate: (id: number, data: any) =>
        apiClient.post(`/voucher-types/${id}/validate`, data),
}

// Search API
export const searchApi = {
    global: (query: string, companyCode?: string) =>
        apiClient.get('/search', { params: { query, company_code: companyCode } }),
    vouchers: (query: string, companyCode?: string) =>
        apiClient.get('/search/vouchers', { params: { query, company_code: companyCode } }),
    accounts: (query: string, companyCode?: string) =>
        apiClient.get('/search/accounts', { params: { query, company_code: companyCode } }),
    ledgers: (query: string, companyCode?: string) =>
        apiClient.get('/search/ledgers', { params: { query, company_code: companyCode } }),
}

// Print API
export const printApi = {
    voucher: (id: string) => apiClient.get(`/print/voucher/${id}`, {
        responseType: 'blob'
    }),
    report: (type: string, data: any) =>
        apiClient.post('/print/report', { report_type: type, data }, {
            responseType: 'blob'
        }),
    getTemplate: (type: string, companyCode?: string) =>
        apiClient.get('/print/template', { params: { template_type: type, company_code: companyCode } }),
    saveTemplate: (data: any) => apiClient.post('/print/template', data),
    uploadLogo: (file: File, companyCode?: string) => {
        const formData = new FormData()
        formData.append('logo', file)
        if (companyCode) {
            formData.append('company_code', companyCode)
        }
        return apiClient.post('/print/upload-logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
    },
}

// Report API
export const reportApi = {
    trialBalance: (asOfDate: string, currency: string) =>
        apiClient.get('/reports/trial-balance', { params: { as_of_date: asOfDate, currency } }),
    profitLoss: (fromDate: string, toDate: string, currency: string) =>
        apiClient.get('/reports/profit-loss', { params: { from_date: fromDate, to_date: toDate, currency } }),
    balanceSheet: (asOfDate: string, currency: string) =>
        apiClient.get('/reports/balance-sheet', { params: { as_of_date: asOfDate, currency } }),
    ledger: (accountCode: string, fromDate: string, toDate: string, currency: string) =>
        apiClient.get('/reports/ledger', { params: { account_code: accountCode, from_date: fromDate, to_date: toDate, currency } }),
    receivables: (asOfDate: string, currency: string = 'PKR') =>
        apiClient.get('/reports/receivables', { params: { as_of_date: asOfDate, currency } }),
    payables: (asOfDate: string, currency: string = 'PKR') =>
        apiClient.get('/reports/payables', { params: { as_of_date: asOfDate, currency } }),
}
