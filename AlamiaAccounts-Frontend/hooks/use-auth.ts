import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authApi } from '@/lib/api'

export function useAuth() {
    const queryClient = useQueryClient()

    const login = useMutation({
        mutationFn: ({ email, password }: { email: string; password: string }) =>
            authApi.login(email, password),
        onSuccess: (response) => {
            if (typeof window !== 'undefined') {
                localStorage.setItem('auth_token', response.data.token)
            }
            queryClient.invalidateQueries({ queryKey: ['user'] })
        },
    })

    const logout = useMutation({
        mutationFn: () => authApi.logout(),
        onSuccess: () => {
            if (typeof window !== 'undefined') {
                localStorage.removeItem('auth_token')
            }
            queryClient.clear()
        },
    })

    const { data: user, isLoading } = useQuery({
        queryKey: ['user'],
        queryFn: async () => {
            const response = await authApi.me()
            return response.data
        },
        enabled: typeof window !== 'undefined' && !!localStorage.getItem('auth_token'),
    })

    return {
        login,
        logout,
        user,
        isLoading,
        isAuthenticated: !!user,
    }
}
