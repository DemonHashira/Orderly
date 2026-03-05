import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { PaginatedResponse, Product, ProductListParams } from '@/types'

export const fetchProducts = async (
  params: ProductListParams = {},
): Promise<PaginatedResponse<Product>> => {
  const { data } = await apiClient.get<PaginatedResponse<Product>>('/api/products', {
    params: compactParams(params),
  })
  return data
}

export const fetchProduct = async (id: number): Promise<{ data: Product }> => {
  const { data } = await apiClient.get<{ data: Product }>(`/api/products/${id}`)
  return data
}

export const createProduct = async (payload: Partial<Product>): Promise<{ data: Product }> => {
  const { data } = await apiClient.post<{ data: Product }>('/api/products', payload)
  return data
}

export const updateProduct = async (
  id: number,
  payload: Partial<Product>,
): Promise<{ data: Product }> => {
  const { data } = await apiClient.patch<{ data: Product }>(`/api/products/${id}`, payload)
  return data
}

export const archiveProduct = async (id: number): Promise<{ data: Product }> => {
  const { data } = await apiClient.post<{ data: Product }>(`/api/products/${id}/archive`)
  return data
}
