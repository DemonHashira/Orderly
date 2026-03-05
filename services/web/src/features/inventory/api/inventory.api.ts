import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type {
  InventoryMovement,
  InventoryMovementsListParams,
  InventoryStock,
  InventoryStocksListParams,
  PaginatedResponse,
} from '@/types'

export const fetchInventoryStocks = async (
  params: InventoryStocksListParams = {},
): Promise<PaginatedResponse<InventoryStock>> => {
  const { data } = await apiClient.get<PaginatedResponse<InventoryStock>>('/api/inventory/stocks', {
    params: compactParams(params),
  })
  return data
}

export const fetchInventoryMovements = async (
  params: InventoryMovementsListParams = {},
): Promise<PaginatedResponse<InventoryMovement>> => {
  const { data } = await apiClient.get<PaginatedResponse<InventoryMovement>>(
    '/api/inventory/movements',
    {
      params: compactParams(params),
    },
  )
  return data
}

export type CreateMovementPayload = {
  product_id: number
  type: 'adjustment' | 'damage' | 'restock'
  quantity_delta: number
  reason: string
}

export const createInventoryMovement = async (payload: CreateMovementPayload) => {
  const { data } = await apiClient.post<{
    data: {
      movement: InventoryMovement
      stock: InventoryStock
    }
  }>('/api/inventory/movements', payload)

  return data
}
