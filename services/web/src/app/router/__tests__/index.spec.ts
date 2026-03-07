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

  it('allows authenticated users to access account security route', async () => {
    const { router, fetchMe } = await loadRouter()
    fetchMe.mockResolvedValue({
      user: {
        id: 1,
        organization_id: 1,
        email: 'user@example.com',
        first_name: 'User',
        middle_name: null,
        last_name: 'Example',
        is_active: true,
      },
      roles: [],
      permissions: [],
    })

    await router.replace('/account/security')

    expect(router.currentRoute.value.path).toBe('/account/security')
  })

  it('redirects unauthenticated users to login for account security route', async () => {
    const { router, fetchMe } = await loadRouter()
    fetchMe.mockRejectedValue({
      isAxiosError: true,
      response: { status: 401, data: {} },
    })

    await router.replace('/account/security')

    expect(router.currentRoute.value.path).toBe('/login')
    expect(router.currentRoute.value.query.redirect).toBe('/account/security')
  })

  it('allows order-create route with orders.create permission', async () => {
    const { router, fetchMe } = await loadRouter()
    fetchMe.mockResolvedValue({
      user: {
        id: 1,
        organization_id: 1,
        email: 'user@example.com',
        first_name: 'User',
        middle_name: null,
        last_name: 'Example',
        is_active: true,
      },
      roles: [],
      permissions: ['orders.create'],
    })

    await router.replace('/orders/new')

    expect(router.currentRoute.value.path).toBe('/orders/new')
  })

  it('blocks order-edit route without orders.update permission', async () => {
    const { router, fetchMe } = await loadRouter()
    fetchMe.mockResolvedValue({
      user: {
        id: 1,
        organization_id: 1,
        email: 'user@example.com',
        first_name: 'User',
        middle_name: null,
        last_name: 'Example',
        is_active: true,
      },
      roles: [],
      permissions: ['orders.view'],
    })

    await router.replace('/orders/12/edit')

    expect(router.currentRoute.value.path).toBe('/forbidden')
  })
})
