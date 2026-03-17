import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { lookupKeys } from '@/lib/query-keys'
import { fetchOrderCreateLookup } from '@/features/lookups/api/lookups.api'

export const useOrderCreateLookupQuery = (enabled?: MaybeRefOrGetter<boolean>) => {
  const isEnabled = computed(() => (enabled === undefined ? true : Boolean(toValue(enabled))))

  return useQuery({
    queryKey: lookupKeys.orderCreate(),
    queryFn: fetchOrderCreateLookup,
    enabled: isEnabled,
    staleTime: 30 * 60 * 1000,
  })
}
