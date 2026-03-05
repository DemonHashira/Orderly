import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import * as authApi from '@/features/auth/api/auth.api'
import { useLoginMutation } from '../useLoginMutation'

const mockAuthData = {
  user: {
    id: 1,
    organization_id: 1,
    email: 'test@example.com',
    first_name: 'Test',
    middle_name: null,
    last_name: 'User',
    is_active: true,
  },
  roles: ['admin'],
  permissions: ['dashboard.view'],
}

describe('useLoginMutation', () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  })

  beforeEach(() => {
    vi.restoreAllMocks()
    queryClient.clear()
    vi.spyOn(authApi, 'getCsrfCookie').mockResolvedValue(undefined)
    vi.spyOn(authApi, 'login').mockResolvedValue(undefined)
    vi.spyOn(authApi, 'fetchMe').mockResolvedValue(mockAuthData)
  })

  it('calls login API and syncs auth/me on success', async () => {
    const setQueryDataSpy = vi.spyOn(queryClient, 'setQueryData')

    const TestComponent = defineComponent({
      setup() {
        const mutation = useLoginMutation()
        return () =>
          h('button', {
            'data-testid': 'login-btn',
            onClick: () => mutation.mutate({ email: 'a@b.com', password: 'secret' }),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="login-btn"]').trigger('click')
    await flushPromises()

    expect(authApi.getCsrfCookie).toHaveBeenCalledTimes(1)
    expect(authApi.login).toHaveBeenCalledWith({ email: 'a@b.com', password: 'secret' })
    expect(authApi.fetchMe).toHaveBeenCalledTimes(1)
    expect(setQueryDataSpy).toHaveBeenCalledWith(['auth', 'me'], mockAuthData)
  })

  it('fails login when auth/me never establishes an authenticated session', async () => {
    vi.spyOn(authApi, 'fetchMe').mockRejectedValue(new Error('Session not ready'))

    let loginMutation: ReturnType<typeof useLoginMutation> | undefined

    const TestComponent = defineComponent({
      setup() {
        loginMutation = useLoginMutation()
        return () => h('div')
      },
    })

    mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    if (!loginMutation) {
      throw new Error('Mutation was not initialized')
    }

    await expect(
      loginMutation.mutateAsync({ email: 'a@b.com', password: 'secret' }),
    ).rejects.toThrow('Authenticated session could not be established. Please try again.')
    expect(authApi.login).toHaveBeenCalledWith({ email: 'a@b.com', password: 'secret' })
  })
})
