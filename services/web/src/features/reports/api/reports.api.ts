import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type {
  DateRangeParams,
  InventorySummaryResponse,
  OrdersSummaryResponse,
  ReturnsSummaryResponse,
} from '@/types'

export const fetchOrdersReportSummary = async (
  params: DateRangeParams = {},
): Promise<OrdersSummaryResponse> => {
  const { data } = await apiClient.get<OrdersSummaryResponse>('/api/reports/orders/summary', {
    params: compactParams(params),
  })

  return data
}

export const fetchInventoryReportSummary = async (
  params: DateRangeParams = {},
): Promise<InventorySummaryResponse> => {
  const { data } = await apiClient.get<InventorySummaryResponse>('/api/reports/inventory/summary', {
    params: compactParams(params),
  })

  return data
}

export const fetchReturnsReportSummary = async (
  params: DateRangeParams = {},
): Promise<ReturnsSummaryResponse> => {
  const { data } = await apiClient.get<ReturnsSummaryResponse>('/api/reports/returns/summary', {
    params: compactParams(params),
  })

  return data
}
