import { useQuery } from '@tanstack/vue-query'
import { lookupKeys } from '@/lib/query-keys'
import { fetchOrderCreateLookup } from '@/features/lookups/api/lookups.api'

export const useOrderCreateLookupQuery = () => {
  return useQuery({
    queryKey: lookupKeys.orderCreate(),
    queryFn: fetchOrderCreateLookup,
    staleTime: 30 * 60 * 1000,
  })
}
