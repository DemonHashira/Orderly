import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { salesChannelsKeys } from '@/lib/query-keys'
import { fetchSalesChannels } from '@/features/sales-channels/api/sales-channels.api'

export const useSalesChannelsQuery = (q: MaybeRefOrGetter<string | undefined>) => {
  const query = computed(() => toValue(q) ?? '')

  return useQuery({
    queryKey: computed(() => salesChannelsKeys.list({ q: query.value })),
    queryFn: () => fetchSalesChannels({ q: query.value }),
    staleTime: 30 * 60 * 1000,
  })
}
