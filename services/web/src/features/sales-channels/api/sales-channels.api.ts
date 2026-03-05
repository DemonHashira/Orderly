import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { SalesChannel } from '@/types'

export const fetchSalesChannels = async (
  params: { q?: string } = {},
): Promise<{ data: SalesChannel[] }> => {
  const { data } = await apiClient.get<{ data: SalesChannel[] }>('/api/sales-channels', {
    params: compactParams(params),
  })

  return data
}
