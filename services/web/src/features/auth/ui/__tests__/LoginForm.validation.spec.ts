import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { reactive, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import LoginForm from '../LoginForm.vue'

const defineFieldMock = vi.fn((field: string) => [ref(''), { name: field }])
const setErrorsMock = vi.fn()
const setFieldValueMock = vi.fn()
const mutateAsyncMock = vi.fn()

vi.mock('@vee-validate/zod', () => ({
  toTypedSchema: vi.fn((schema) => schema),
}))

vi.mock('vee-validate', () => ({
  useForm: vi.fn(() => ({
    defineField: defineFieldMock,
    errors: ref<Record<string, string | undefined>>({}),
    handleSubmit: vi.fn((callback) => callback),
    setErrors: setErrorsMock,
    values: reactive({
      email: '',
      password: '',
      remember: false,
    }),
    setFieldValue: setFieldValueMock,
  })),
}))

vi.mock('@/features/auth/composables/useLoginMutation', () => ({
  useLoginMutation: () => ({
    mutateAsync: mutateAsyncMock,
    isPending: ref(false),
  }),
}))

describe('LoginForm field validation timing', () => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  beforeEach(() => {
    vi.clearAllMocks()
  })

  const mountForm = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/login', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
      ],
    })

    await router.replace('/login')

    return mount(LoginForm, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }], router],
      },
    })
  }

  it('configures email validation to run on blur instead of each model update', async () => {
    await mountForm()

    expect(defineFieldMock).toHaveBeenCalledWith(
      'email',
      expect.objectContaining({
        validateOnBlur: true,
        validateOnModelUpdate: false,
      }),
    )
  })
})
