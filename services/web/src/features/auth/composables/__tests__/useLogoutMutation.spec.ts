import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import * as authApi from '@/features/auth/api/auth.api'
import { useLogoutMutation } from '../useLogoutMutation'

describe('useLogoutMutation', () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  })

  beforeEach(() => {
    vi.restoreAllMocks()
    queryClient.clear()
    vi.spyOn(authApi, 'getCsrfCookie').mockResolvedValue(undefined)
    vi.spyOn(authApi, 'logout').mockResolvedValue(undefined)
  })

  it('calls logout API and removes auth/me from cache on success', async () => {
    const removeQueriesSpy = vi.spyOn(queryClient, 'removeQueries')

    const TestComponent = defineComponent({
      setup() {
        const mutation = useLogoutMutation()
        return () =>
          h('button', {
            'data-testid': 'logout-btn',
            onClick: () => mutation.mutate(),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="logout-btn"]').trigger('click')
    await flushPromises()

    expect(authApi.getCsrfCookie).toHaveBeenCalledTimes(1)
    expect(authApi.logout).toHaveBeenCalledTimes(1)
    expect(removeQueriesSpy).toHaveBeenCalledWith({ queryKey: ['auth', 'me'] })
  })

  it('treats 401 logout as graceful and still clears auth cache', async () => {
    const removeQueriesSpy = vi.spyOn(queryClient, 'removeQueries')
    vi.spyOn(authApi, 'logout').mockRejectedValueOnce({
      isAxiosError: true,
      response: { status: 401, data: {} },
    })

    const TestComponent = defineComponent({
      setup() {
        const mutation = useLogoutMutation()
        return () =>
          h('button', {
            'data-testid': 'logout-btn',
            onClick: () => mutation.mutate(),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="logout-btn"]').trigger('click')
    await flushPromises()

    expect(removeQueriesSpy).toHaveBeenCalledWith({ queryKey: ['auth', 'me'] })
  })

  it('refreshes csrf and retries logout once on 419', async () => {
    const removeQueriesSpy = vi.spyOn(queryClient, 'removeQueries')
    vi.spyOn(authApi, 'logout')
      .mockRejectedValueOnce({
        isAxiosError: true,
        response: { status: 419, data: {} },
      })
      .mockResolvedValueOnce(undefined)

    const TestComponent = defineComponent({
      setup() {
        const mutation = useLogoutMutation()
        return () =>
          h('button', {
            'data-testid': 'logout-btn',
            onClick: () => mutation.mutate(),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="logout-btn"]').trigger('click')
    await flushPromises()

    expect(authApi.getCsrfCookie).toHaveBeenCalledTimes(2)
    expect(authApi.logout).toHaveBeenCalledTimes(2)
    expect(removeQueriesSpy).toHaveBeenCalledWith({ queryKey: ['auth', 'me'] })
  })
})
