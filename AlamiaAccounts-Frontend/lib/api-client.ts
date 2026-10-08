import axios from 'axios'

const getInitialBaseUrl = () => {
    if (typeof window !== 'undefined') {
        return process.env.NEXT_PUBLIC_API_URL || `${window.location.origin}/api`
    }
    return process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
}

const apiClient = axios.create({
    baseURL: getInitialBaseUrl(),
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
})

// Add auth token, current company code, and dynamic origin to requests
apiClient.interceptors.request.use((config) => {
    if (typeof window !== 'undefined') {
        if (!process.env.NEXT_PUBLIC_API_URL) {
            config.baseURL = `${window.location.origin}/api`
        }
        const token = localStorage.getItem('auth_token')
        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        }
        const companyCode = localStorage.getItem('current_company_code')
        if (companyCode) {
            config.headers['X-Company-Code'] = companyCode
        }
    }
    return config
})

// Handle 401 errors
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            if (typeof window !== 'undefined') {
                localStorage.removeItem('auth_token')
                window.location.href = '/login'
            }
        }
        return Promise.reject(error)
    }
)

export { apiClient }
export default apiClient
