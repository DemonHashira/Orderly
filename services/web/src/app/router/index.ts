import { createRouter, createWebHistory } from 'vue-router'
import { queryClient } from '@/lib/query-client'
import { authKeys } from '@/lib/query-keys'
import { fetchMe } from '@/features/auth/api/auth.api'
import { normalizeApiError } from '@/shared/api/errors'
import DashboardView from '@/views/DashboardView.vue'
import ForbiddenView from '@/views/ForbiddenView.vue'
import LoginView from '@/views/LoginView.vue'
import '@/app/router/route-meta'

const FIVE_MINUTES = 5 * 60 * 1000

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: {
        requiresAuth: true,
        permission: 'dashboard.view',
      },
    },
    {
      path: '/forbidden',
      name: 'forbidden',
      component: ForbiddenView,
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/login',
    },
  ],
})

router.beforeEach(async (to) => {
  if (to.name === 'login') {
    const cachedAuth = queryClient.getQueryData<Awaited<ReturnType<typeof fetchMe>>>(authKeys.me())
    if (cachedAuth?.user) {
      return '/dashboard'
    }

    try {
      const data = await queryClient.ensureQueryData({
        queryKey: authKeys.me(),
        queryFn: fetchMe,
        staleTime: FIVE_MINUTES,
        retry: false,
      })

      if (data?.user) {
        return '/dashboard'
      }
    } catch (error: unknown) {
      const normalizedError = normalizeApiError(error)

      if (normalizedError.status === 401 || normalizedError.status === 419) {
        queryClient.removeQueries({ queryKey: authKeys.me() })
        return true
      }
    }

    return true
  }

  if (!to.meta.requiresAuth && !to.meta.permission) {
    return true
  }

  let data: Awaited<ReturnType<typeof fetchMe>> | undefined

  try {
    data = await queryClient.ensureQueryData({
      queryKey: authKeys.me(),
      queryFn: fetchMe,
      staleTime: FIVE_MINUTES,
      retry: false,
    })
  } catch (error: unknown) {
    const normalizedError = normalizeApiError(error)

    if (normalizedError.status === 401 || normalizedError.status === 419) {
      queryClient.removeQueries({ queryKey: authKeys.me() })
      if (to.meta.requiresAuth) {
        return { path: '/login', query: { redirect: to.fullPath } }
      }
      return true
    }

    return true
  }

  const isAuthenticated = data?.user != null

  if (to.meta.requiresAuth && !isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  const permission = typeof to.meta.permission === 'string' ? to.meta.permission : null
  if (permission && !data?.permissions?.includes(permission)) {
    return '/forbidden'
  }

  return true
})

export default router
