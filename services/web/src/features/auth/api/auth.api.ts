import { apiClient } from '@/shared/api/client'
import type { AuthMeResponse, ChangePasswordPayload, LoginPayload } from '@/types/auth'

export const getCsrfCookie = async (): Promise<void> => {
  await apiClient.get('/sanctum/csrf-cookie')
}

export const fetchMe = async (): Promise<AuthMeResponse> => {
  const { data } = await apiClient.get<AuthMeResponse>('/api/auth/me')
  return data
}

export const login = async (payload: LoginPayload): Promise<void> => {
  await apiClient.post('/api/auth/login', payload)
}

export const logout = async (): Promise<void> => {
  await apiClient.post('/api/auth/logout')
}

export const changePassword = async (
  payload: ChangePasswordPayload,
): Promise<{ message: string }> => {
  const { data } = await apiClient.post<{ message: string }>('/api/auth/change-password', payload)
  return data
}
