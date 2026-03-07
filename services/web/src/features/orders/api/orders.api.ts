import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { Order, OrderListParams, PaginatedResponse } from '@/types'
import type { OrderUpsertPayload } from '@/features/orders/types'

export const fetchOrders = async (
  params: OrderListParams = {},
): Promise<PaginatedResponse<Order>> => {
  const { data } = await apiClient.get<PaginatedResponse<Order>>('/api/orders', {
    params: compactParams(params),
  })
  return data
}

export const fetchOrder = async (id: number): Promise<{ data: Order }> => {
  const { data } = await apiClient.get<{ data: Order }>(`/api/orders/${id}`)
  return data
}

export const confirmOrder = async (id: number): Promise<{ data: Order }> => {
  const { data } = await apiClient.post<{ data: Order }>(`/api/orders/${id}/confirm`)
  return data
}

export const readyToShipOrder = async (id: number): Promise<{ data: Order }> => {
  const { data } = await apiClient.post<{ data: Order }>(`/api/orders/${id}/ready-to-ship`)
  return data
}

export const cancelOrder = async (id: number): Promise<{ data: Order }> => {
  const { data } = await apiClient.post<{ data: Order }>(`/api/orders/${id}/cancel`)
  return data
}

export const createOrder = async (payload: OrderUpsertPayload): Promise<{ data: Order }> => {
  const { data } = await apiClient.post<{ data: Order }>('/api/orders', payload)
  return data
}

export const updateOrder = async (
  id: number,
  payload: OrderUpsertPayload,
): Promise<{ data: Order }> => {
  const { data } = await apiClient.put<{ data: Order }>(`/api/orders/${id}`, payload)
  return data
}

export const deleteOrder = async (id: number): Promise<void> => {
  await apiClient.delete(`/api/orders/${id}`)
}
