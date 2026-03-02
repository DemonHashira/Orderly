import { useQuery } from '@tanstack/vue-query'
import { authKeys } from '@/lib/query-keys'
import { fetchMe } from '@/features/auth/api/auth.api'

const FIVE_MINUTES = 5 * 60 * 1000

export const useAuthMeQuery = () => {
  return useQuery({
    queryKey: authKeys.me(),
    queryFn: fetchMe,
    staleTime: FIVE_MINUTES,
    retry: false,
  })
}
