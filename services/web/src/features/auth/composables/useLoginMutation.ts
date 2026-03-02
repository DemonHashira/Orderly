import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { authKeys } from '@/lib/query-keys'
import { getCsrfCookie, login as loginApi } from '@/features/auth/api/auth.api'
import type { LoginPayload } from '@/types/auth'

export const useLoginMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LoginPayload) => {
      await getCsrfCookie()
      await loginApi(payload)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: authKeys.me() })
    },
  })
}
