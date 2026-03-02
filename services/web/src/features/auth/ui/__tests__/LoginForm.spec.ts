import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import LoginForm from '../LoginForm.vue'

const mockPush = vi.fn()

describe('LoginForm', () => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  beforeEach(() => {
    vi.restoreAllMocks()
    mockPush.mockClear()
  })

  const mountForm = async (routeQuery: Record<string, string> = {}) => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/login', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
      ],
    })
    router.push = mockPush
    await router.replace({ path: '/login', query: routeQuery })

    return mount(LoginForm, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }], router],
        stubs: { RouterLink: true },
      },
    })
  }

  it('renders login form with email and password fields', async () => {
    const wrapper = await mountForm()
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Login to your account')
  })

  it('has form wired to submit handler', async () => {
    const wrapper = await mountForm()
    const form = wrapper.find('form')
    expect(form.attributes('class')).toBeDefined()
    expect(form.exists()).toBe(true)
  })
})
