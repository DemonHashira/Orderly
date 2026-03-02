import { computed } from 'vue'
import { useAuthMeQuery } from './useAuthMeQuery'

export const useAuth = () => {
  const { data, isLoading, error, refetch } = useAuthMeQuery()

  const user = computed(() => data.value?.user ?? null)
  const isAuthenticated = computed(() => data.value?.user != null)
  const roles = computed(() => data.value?.roles ?? [])
  const permissions = computed(() => data.value?.permissions ?? [])

  return {
    data,
    user,
    isAuthenticated,
    roles,
    permissions,
    isLoading,
    error,
    refetch,
  }
}
