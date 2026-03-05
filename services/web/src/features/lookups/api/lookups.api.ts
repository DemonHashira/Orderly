import { apiClient } from '@/shared/api/client'
import type { LookupOrderCreate } from '@/types'

export const fetchOrderCreateLookup = async (): Promise<{ data: LookupOrderCreate }> => {
  const { data } = await apiClient.get<{ data: LookupOrderCreate }>('/api/lookups/order-create')
  return data
}
