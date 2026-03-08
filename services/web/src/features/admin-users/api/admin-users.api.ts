import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type {
  CreateAdminUserPayload,
  UpdateAdminUserPayload,
  UpdateAdminUserRolePayload,
  UpdateAdminUserStatusPayload,
} from '@/features/admin-users/types'
import type {
  AdminRole,
  AdminUser,
  AdminUsersListParams,
  PaginatedResponse,
  ResourceResponse,
} from '@/types'

export const fetchAdminUsers = async (
  params: AdminUsersListParams = {},
): Promise<PaginatedResponse<AdminUser>> => {
  const { data } = await apiClient.get<PaginatedResponse<AdminUser>>('/api/admin/users', {
    params: compactParams(params),
  })

  return data
}

export const createAdminUser = async (
  payload: CreateAdminUserPayload,
): Promise<ResourceResponse<AdminUser>> => {
  const { data } = await apiClient.post<ResourceResponse<AdminUser>>('/api/admin/users', payload)
  return data
}

export const updateAdminUser = async (
  id: number,
  payload: UpdateAdminUserPayload,
): Promise<ResourceResponse<AdminUser>> => {
  const { data } = await apiClient.patch<ResourceResponse<AdminUser>>(
    `/api/admin/users/${id}`,
    payload,
  )
  return data
}

export const updateAdminUserStatus = async (
  id: number,
  payload: UpdateAdminUserStatusPayload,
): Promise<ResourceResponse<AdminUser>> => {
  const { data } = await apiClient.patch<ResourceResponse<AdminUser>>(
    `/api/admin/users/${id}/status`,
    payload,
  )

  return data
}

export const fetchAdminRoles = async (): Promise<{ data: AdminRole[] }> => {
  const { data } = await apiClient.get<{ data: AdminRole[] }>('/api/admin/roles')
  return data
}

export const updateAdminUserRole = async (
  id: number,
  payload: UpdateAdminUserRolePayload,
): Promise<ResourceResponse<AdminUser>> => {
  const { data } = await apiClient.patch<ResourceResponse<AdminUser>>(
    `/api/admin/users/${id}/role`,
    payload,
  )

  return data
}
