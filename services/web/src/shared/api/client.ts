import axios from 'axios'
import { queryClient } from '@/lib/query-client'
import { authKeys } from '@/lib/query-keys'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status as number | undefined

    if ((status === 401 || status === 419) && typeof window !== 'undefined') {
      queryClient.removeQueries({ queryKey: authKeys.me() })

      const path = window.location.pathname ?? '/'
      if (path !== '/login') {
        const redirectTarget = `${path}${window.location.search}${window.location.hash}`
        const loginUrl = `/login?redirect=${encodeURIComponent(redirectTarget)}`
        const currentUrl = `${window.location.pathname}${window.location.search}`

        if (currentUrl !== loginUrl) {
          window.location.assign(loginUrl)
        }
      }
    }

    return Promise.reject(error)
  },
)
