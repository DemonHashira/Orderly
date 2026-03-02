import { computed } from 'vue'
import { useAuthMeQuery } from './useAuthMeQuery'

export const usePermission = (permission: string) => {
  const { data } = useAuthMeQuery()
  return computed(() => (data.value?.permissions?.includes(permission) ?? false) as boolean)
}
