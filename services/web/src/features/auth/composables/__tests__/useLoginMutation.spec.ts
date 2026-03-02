import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import * as authApi from '@/features/auth/api/auth.api'
import { useLoginMutation } from '../useLoginMutation'

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
  })

  it('calls login API and invalidates auth/me on success', async () => {
    const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')

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
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['auth', 'me'] })
  })
})
