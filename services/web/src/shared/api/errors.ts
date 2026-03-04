import axios from 'axios'
import type { ApiValidationError, NormalizedApiError } from '@/types/api'

const fallbackMessageForStatus = (status: number | null): string => {
  if (status === 401) return 'Session expired. Please log in again.'
  if (status === 419) return 'CSRF token missing or expired. Refresh and try again.'
  return status ? `Request failed (${status})` : 'Request failed'
}

export const normalizeApiError = (err: unknown): NormalizedApiError => {
  if (!axios.isAxiosError(err)) {
    if (err instanceof Error) {
      return {
        status: null,
        message: err.message || 'Unexpected error',
      }
    }

    return {
      status: null,
      message: 'Unexpected error',
    }
  }

  const status = err.response?.status ?? null
  const data = err.response?.data as ApiValidationError | undefined
  const firstKey = data?.errors ? Object.keys(data.errors)[0] : undefined
  const firstFieldMessage = firstKey ? data?.errors?.[firstKey]?.[0] : undefined

  return {
    status,
    message: data?.message ?? firstFieldMessage ?? fallbackMessageForStatus(status),
    fieldErrors: data?.errors,
  }
}

export const pickErrorMessage = (err: unknown): string => normalizeApiError(err).message
