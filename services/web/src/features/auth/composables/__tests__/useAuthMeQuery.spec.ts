import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import * as authApi from '@/features/auth/api/auth.api'
import { useAuthMeQuery } from '../useAuthMeQuery'

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

describe('useAuthMeQuery', () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  })

  beforeEach(() => {
    vi.restoreAllMocks()
    queryClient.clear()
  })

  it('fetches auth data on mount and returns it', async () => {
    vi.spyOn(authApi, 'fetchMe').mockResolvedValue(mockAuthData)

    const TestComponent = defineComponent({
      setup() {
        const { data, isSuccess } = useAuthMeQuery()
        return () =>
          h('div', {}, [
            h('span', { 'data-success': String(isSuccess.value) }),
            data.value?.user ? h('span', { 'data-user': data.value.user.email }) : null,
          ])
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await flushPromises()

    expect(authApi.fetchMe).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-success="true"]').exists()).toBe(true)
    expect(wrapper.find('[data-user="test@example.com"]').exists()).toBe(true)
  })
})
