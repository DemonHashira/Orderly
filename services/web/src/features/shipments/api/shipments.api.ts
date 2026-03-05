import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { PaginatedResponse, Shipment, ShipmentListParams } from '@/types'

export const fetchShipments = async (
  params: ShipmentListParams = {},
): Promise<PaginatedResponse<Shipment>> => {
  const { data } = await apiClient.get<PaginatedResponse<Shipment>>('/api/shipments', {
    params: compactParams(params),
  })
  return data
}

export const fetchShipment = async (id: number): Promise<{ data: Shipment }> => {
  const { data } = await apiClient.get<{ data: Shipment }>(`/api/shipments/${id}`)
  return data
}

export const markShipmentDelivered = async (id: number): Promise<{ data: Shipment }> => {
  const { data } = await apiClient.post<{ data: Shipment }>(`/api/shipments/${id}/delivered`)
  return data
}

export const markShipmentReturned = async (
  id: number,
): Promise<{ shipment: { data: Shipment } }> => {
  const { data } = await apiClient.post<{ shipment: { data: Shipment } }>(
    `/api/shipments/${id}/returned`,
    {
      reason: 'Returned by customer',
    },
  )
  return data
}

export const markShipmentUnpaid = async (id: number): Promise<{ shipment: { data: Shipment } }> => {
  const { data } = await apiClient.post<{ shipment: { data: Shipment } }>(
    `/api/shipments/${id}/unpaid`,
    {
      reason: 'Marked unpaid',
    },
  )
  return data
}
