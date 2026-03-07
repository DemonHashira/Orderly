import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import * as authApi from '@/features/auth/api/auth.api'
import { useChangePasswordMutation } from '../useChangePasswordMutation'

describe('useChangePasswordMutation', () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  })

  beforeEach(() => {
    vi.restoreAllMocks()
    queryClient.clear()
    vi.spyOn(authApi, 'getCsrfCookie').mockResolvedValue(undefined)
    vi.spyOn(authApi, 'changePassword').mockResolvedValue({
      message: 'Password changed successfully.',
    })
  })

  it('calls csrf then change password api', async () => {
    const payload = {
      current_password: 'Old-password-123!',
      password: 'New-password-123!',
      password_confirmation: 'New-password-123!',
    }

    const TestComponent = defineComponent({
      setup() {
        const mutation = useChangePasswordMutation()
        return () =>
          h('button', {
            'data-testid': 'change-password-btn',
            onClick: () => mutation.mutate(payload),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="change-password-btn"]').trigger('click')
    await flushPromises()

    expect(authApi.getCsrfCookie).toHaveBeenCalledTimes(1)
    expect(authApi.changePassword).toHaveBeenCalledWith(payload)
  })

  it('propagates api errors', async () => {
    vi.spyOn(authApi, 'changePassword').mockRejectedValueOnce(new Error('Request failed'))

    let mutation: ReturnType<typeof useChangePasswordMutation> | undefined

    const TestComponent = defineComponent({
      setup() {
        mutation = useChangePasswordMutation()
        return () => h('div')
      },
    })

    mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    if (!mutation) {
      throw new Error('Mutation was not initialized')
    }

    await expect(
      mutation.mutateAsync({
        current_password: 'Old-password-123!',
        password: 'New-password-123!',
        password_confirmation: 'New-password-123!',
      }),
    ).rejects.toThrow('Request failed')
  })
})
