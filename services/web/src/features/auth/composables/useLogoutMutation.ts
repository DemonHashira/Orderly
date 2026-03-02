import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { authKeys } from '@/lib/query-keys'
import { logout as logoutApi } from '@/features/auth/api/auth.api'
import { normalizeApiError } from '@/shared/api/errors'

export const useLogoutMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      try {
        await logoutApi()
      } catch (error: unknown) {
        const normalizedError = normalizeApiError(error)

        // Treat already-logged-out as a successful local logout.
        if (normalizedError.status === 401 || normalizedError.status === 419) {
          return
        }

        throw error
      }
    },
    onSettled: () => {
      queryClient.removeQueries({ queryKey: authKeys.me() })
    },
  })
}
