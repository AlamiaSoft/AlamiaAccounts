import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { userApi } from '@/lib/api'

export interface ApiUser {
    id: string | number
    name: string
    email: string
    role: "admin" | "accountant" | "viewer"
    status: "active" | "inactive"
    created_at?: string
}

export function useUsers() {
    const queryClient = useQueryClient()

    const { data: users, isLoading } = useQuery({
        queryKey: ['users'],
        queryFn: async () => {
            const response = await userApi.getAll()
            return (response.data || []) as ApiUser[]
        },
    })

    const createUser = useMutation({
        mutationFn: (data: any) => userApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
        },
    })

    const updateUser = useMutation({
        mutationFn: ({ id, data }: { id: string | number; data: any }) =>
            userApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
        },
    })

    const deleteUser = useMutation({
        mutationFn: (id: string | number) => userApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
        },
    })

    return {
        users: users || [],
        isLoading,
        createUser,
        updateUser,
        deleteUser,
    }
}
