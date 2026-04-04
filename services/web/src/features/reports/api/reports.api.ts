import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { DateRangeParams } from '@/types'
import type {
  InventoryReportSummaryResponse,
  OrdersReportSummaryResponse,
  ReturnsReportSummaryResponse,
} from '@/features/reports/model/report-types'

export const fetchOrdersReportSummary = async (
  params: DateRangeParams = {},
): Promise<OrdersReportSummaryResponse> => {
  const { data } = await apiClient.get<OrdersReportSummaryResponse>('/api/reports/orders/summary', {
    params: compactParams(params),
  })

  return data
}

export const fetchInventoryReportSummary = async (
  params: DateRangeParams = {},
): Promise<InventoryReportSummaryResponse> => {
  const { data } = await apiClient.get<InventoryReportSummaryResponse>(
    '/api/reports/inventory/summary',
    {
      params: compactParams(params),
    },
  )

  return data
}

export const fetchReturnsReportSummary = async (
  params: DateRangeParams = {},
): Promise<ReturnsReportSummaryResponse> => {
  const { data } = await apiClient.get<ReturnsReportSummaryResponse>(
    '/api/reports/returns/summary',
    {
      params: compactParams(params),
    },
  )

  return data
}
