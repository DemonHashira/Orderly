import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { DashboardSummaryResponse, DateRangeParams } from '@/types'

export const fetchDashboardSummary = async (
  params: DateRangeParams = {},
): Promise<DashboardSummaryResponse> => {
  const { data } = await apiClient.get<DashboardSummaryResponse>('/api/dashboard', {
    params: compactParams(params),
  })

  return data
}
