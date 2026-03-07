import { useMutation } from '@tanstack/vue-query'
import { changePassword as changePasswordApi, getCsrfCookie } from '@/features/auth/api/auth.api'
import type { ChangePasswordPayload } from '@/types/auth'

export const useChangePasswordMutation = () => {
  return useMutation({
    mutationFn: async (payload: ChangePasswordPayload) => {
      await getCsrfCookie()
      return changePasswordApi(payload)
    },
  })
}
