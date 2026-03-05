import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { authKeys } from '@/lib/query-keys'
import { fetchMe, getCsrfCookie, login as loginApi } from '@/features/auth/api/auth.api'
import type { LoginPayload } from '@/types/auth'
import type { AuthMeResponse } from '@/types/auth'

const wait = (ms: number): Promise<void> =>
  new Promise((resolve) => {
    setTimeout(resolve, ms)
  })

const fetchAuthenticatedUser = async (): Promise<AuthMeResponse | null> => {
  for (let attempt = 0; attempt < 15; attempt += 1) {
    try {
      const me = await fetchMe()
      if (me.user) {
        return me
      }
    } catch {
      // Keep retrying briefly while the session cookie settles.
    }

    await wait(200)
  }

  return null
}

export const useLoginMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LoginPayload) => {
      await getCsrfCookie()
      await loginApi(payload)

      const me = await fetchAuthenticatedUser()

      if (me) {
        queryClient.setQueryData(authKeys.me(), me)
        return
      }

      throw new Error('Authenticated session could not be established. Please try again.')
    },
  })
}
