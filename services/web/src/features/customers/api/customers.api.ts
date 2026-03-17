import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type { Customer, CustomerListParams, PaginatedResponse } from '@/types'
import type { CustomerUpsertPayload } from '@/features/customers/types'

export const fetchCustomers = async (
  params: CustomerListParams = {},
): Promise<PaginatedResponse<Customer>> => {
  const { data } = await apiClient.get<PaginatedResponse<Customer>>('/api/customers', {
    params: compactParams(params),
  })
  return data
}

export const fetchCustomer = async (id: number): Promise<{ data: Customer }> => {
  const { data } = await apiClient.get<{ data: Customer }>(`/api/customers/${id}`)
  return data
}

export const createCustomer = async (
  payload: CustomerUpsertPayload,
): Promise<{ data: Customer }> => {
  const { data } = await apiClient.post<{ data: Customer }>('/api/customers', payload)
  return data
}

export const updateCustomer = async (
  id: number,
  payload: CustomerUpsertPayload,
): Promise<{ data: Customer }> => {
  const { data } = await apiClient.put<{ data: Customer }>(`/api/customers/${id}`, payload)
  return data
}

export const deleteCustomer = async (id: number): Promise<void> => {
  await apiClient.delete(`/api/customers/${id}`)
}
