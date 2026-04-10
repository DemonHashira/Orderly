import { beforeEach, describe, expect, it, vi } from 'vitest'

const createAppMock = vi.hoisted(() => vi.fn())
const piniaUseMock = vi.hoisted(() => vi.fn())
const createPiniaMock = vi.hoisted(() => vi.fn())
const createPersistedStateMock = vi.hoisted(() => vi.fn())
const lenisPlugin = vi.hoisted(() => ({ install: vi.fn() }))
const router = vi.hoisted(() => ({ install: vi.fn() }))
const queryClient = vi.hoisted(() => ({}))
const vueQueryPlugin = vi.hoisted(() => ({ install: vi.fn() }))

vi.mock('vue', () => ({
  createApp: createAppMock,
}))

vi.mock('pinia', () => ({
  createPinia: createPiniaMock,
}))

vi.mock('pinia-plugin-persistedstate', () => ({
  createPersistedState: createPersistedStateMock,
}))

vi.mock('@tanstack/vue-query', () => ({
  VueQueryPlugin: vueQueryPlugin,
}))

vi.mock('@/app/router', () => ({
  default: router,
}))

vi.mock('@/lib/query-client', () => ({
  queryClient,
}))

vi.mock('lenis/vue', () => ({
  default: lenisPlugin,
}))

vi.mock('../App.vue', () => ({
  default: {},
}))

describe('main', () => {
  beforeEach(() => {
    vi.resetModules()

    const app = {
      use: vi.fn(),
      mount: vi.fn(),
    }
    const pinia = {
      use: piniaUseMock,
    }

    createAppMock.mockReturnValue(app)
    createPiniaMock.mockReturnValue(pinia)
    createPersistedStateMock.mockReturnValue({ key: 'persisted-state-plugin' })
    piniaUseMock.mockClear()
  })

  it('registers the Lenis Vue plugin before mounting the app', async () => {
    await import('../main')

    const app = createAppMock.mock.results[0]?.value

    expect(app.use).toHaveBeenCalledWith(lenisPlugin)
  })
})
