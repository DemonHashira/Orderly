import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { PaginatedResponse, ReturnListParams, ReturnOrder } from '@/types'
import type { AddReturnItemPayload } from '@/features/returns/types'

export const fetchReturns = async (
  params: ReturnListParams = {},
): Promise<PaginatedResponse<ReturnOrder>> => {
  const { data } = await apiClient.get<PaginatedResponse<ReturnOrder>>('/api/returns', {
    params: compactParams(params),
  })
  return data
}

export const fetchReturn = async (id: number): Promise<{ data: ReturnOrder }> => {
  const { data } = await apiClient.get<{ data: ReturnOrder }>(`/api/returns/${id}`)
  return data
}

export const fetchReturnByOrder = async (orderId: number): Promise<{ data: ReturnOrder }> => {
  const { data } = await apiClient.get<{ data: ReturnOrder }>(`/api/orders/${orderId}/return`)
  return data
}

export const restockReturn = async (id: number): Promise<{ data: ReturnOrder }> => {
  const { data } = await apiClient.post<{ data: ReturnOrder }>(`/api/returns/${id}/restock`)
  return data
}

export const addReturnItem = async (
  id: number,
  payload: AddReturnItemPayload,
): Promise<{ data: ReturnOrder }> => {
  const { data } = await apiClient.post<{ data: ReturnOrder }>(`/api/returns/${id}/items`, payload)
  return data
}
