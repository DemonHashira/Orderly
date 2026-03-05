import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type FetchMeMock = ReturnType<typeof vi.fn>

const loadRouter = async () => {
  vi.resetModules()

  const fetchMe: FetchMeMock = vi.fn()
  vi.doMock('@/features/auth/api/auth.api', () => ({
    fetchMe,
  }))

  const [{ default: router }, { queryClient }, { authKeys }] = await Promise.all([
    import('@/app/router'),
    import('@/lib/query-client'),
    import('@/lib/query-keys'),
  ])

  return { router, queryClient, authKeys, fetchMe }
}

describe('app router login guard', () => {
  beforeEach(async () => {
    const { queryClient } = await import('@/lib/query-client')
    queryClient.clear()
  })

  afterEach(async () => {
    const { queryClient } = await import('@/lib/query-client')
    queryClient.clear()
  })

  it('does not call auth/me when entering /login without cached auth', async () => {
    const { router, fetchMe } = await loadRouter()

    await router.replace('/login')

    expect(router.currentRoute.value.name).toBe('login')
    expect(fetchMe).not.toHaveBeenCalled()
  })

  it('redirects /login to /dashboard when cached authenticated user exists', async () => {
    const { router, queryClient, authKeys, fetchMe } = await loadRouter()

    queryClient.setQueryData(authKeys.me(), {
      user: {
        id: 1,
        organization_id: 1,
        email: 'cached@example.com',
        first_name: 'Cached',
        middle_name: null,
        last_name: 'User',
        is_active: true,
      },
      roles: [],
      permissions: ['dashboard.view'],
    })

    await router.replace('/login')

    expect(router.currentRoute.value.path).toBe('/dashboard')
    expect(fetchMe).not.toHaveBeenCalled()
  })
})
