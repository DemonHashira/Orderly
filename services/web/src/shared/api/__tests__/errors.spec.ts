import { describe, it, expect } from 'vitest'
import { normalizeApiError, pickErrorMessage } from '../errors'

function createAxiosError(response: { status: number; data?: object }) {
  return {
    isAxiosError: true,
    response: { status: response.status, data: response.data ?? {} },
  }
}

describe('normalizeApiError', () => {
  it('returns unexpected error for non-Axios errors', () => {
    const result = normalizeApiError(new Error('generic'))
    expect(result).toEqual({ status: null, message: 'generic' })
  })

  it('returns 401 fallback message when no data message', () => {
    const err = createAxiosError({ status: 401, data: {} })
    const result = normalizeApiError(err)
    expect(result.status).toBe(401)
    expect(result.message).toBe('Session expired. Please log in again.')
  })

  it('returns data message when present', () => {
    const err = createAxiosError({
      status: 422,
      data: { message: 'The given data was invalid.', errors: { email: ['Invalid email'] } },
    })
    const result = normalizeApiError(err)
    expect(result.status).toBe(422)
    expect(result.message).toBe('The given data was invalid.')
    expect(result.fieldErrors).toEqual({ email: ['Invalid email'] })
  })

  it('uses first field error when no message', () => {
    const err = createAxiosError({
      status: 422,
      data: { errors: { email: ['Invalid email'] } },
    })
    const result = normalizeApiError(err)
    expect(result.message).toBe('Invalid email')
    expect(result.fieldErrors).toEqual({ email: ['Invalid email'] })
  })

  it('returns 419 fallback for CSRF', () => {
    const err = createAxiosError({ status: 419, data: {} })
    const result = normalizeApiError(err)
    expect(result.message).toBe('CSRF token missing or expired. Refresh and try again.')
  })
})

describe('pickErrorMessage', () => {
  it('returns message string from normalizeApiError', () => {
    const err = createAxiosError({ status: 500, data: { message: 'Server error' } })
    expect(pickErrorMessage(err)).toBe('Server error')
  })
})
