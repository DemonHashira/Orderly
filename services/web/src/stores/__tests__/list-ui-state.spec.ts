import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { useListUiStateStore } from '../list-ui-state'

const buildTestingPinia = () => {
  const pinia = createPinia()
  pinia.use(
    createPersistedState({
      storage: window.sessionStorage,
    }),
  )
  const app = createApp({})
  app.use(pinia)

  return pinia
}

describe('useListUiStateStore', () => {
  beforeEach(() => {
    setActivePinia(buildTestingPinia())
    window.sessionStorage.clear()
    vi.restoreAllMocks()
  })

  it('hydrates from query and serializes comparable query state', () => {
    const store = useListUiStateStore()
    store.hydrateFromQuery(
      'orders',
      {
        q: 'OC-2026',
        status: 'ready_to_ship',
        sales_channel_id: '4',
        page: '2',
        per_page: '25',
      },
      ['q', 'status', 'sales_channel_id', 'page', 'per_page'],
    )

    expect(store.modules.orders.q).toBe('OC-2026')
    expect(store.modules.orders.status).toBe('ready_to_ship')
    expect(store.modules.orders.sales_channel_id).toBe('4')
    expect(store.modules.orders.page).toBe(2)
    expect(store.modules.orders.per_page).toBe(25)
    expect(
      store.toQuery('orders', ['q', 'status', 'sales_channel_id', 'page', 'per_page']),
    ).toEqual({
      q: 'OC-2026',
      status: 'ready_to_ship',
      sales_channel_id: '4',
      page: '2',
      per_page: '25',
    })
  })

  it('rehydrates persisted module state from session storage', async () => {
    const store = useListUiStateStore()
    store.setState('shipments', { q: 'track', page: 3, per_page: 25 })
    await nextTick()

    setActivePinia(buildTestingPinia())
    const nextStore = useListUiStateStore()
    expect(nextStore.modules.shipments.q).toBe('track')
    expect(nextStore.modules.shipments.page).toBe(3)
    expect(nextStore.modules.shipments.per_page).toBe(25)
  })

  it('persists reset state for a module', () => {
    const store = useListUiStateStore()
    store.setState('products', { q: 'milk', page: 4, per_page: 25 })
    store.reset('products')

    setActivePinia(buildTestingPinia())
    const nextStore = useListUiStateStore()
    expect(nextStore.modules.products.q).toBe('')
    expect(nextStore.modules.products.page).toBe(1)
    expect(nextStore.modules.products.per_page).toBe(15)
  })

  it('keeps query params as precedence over persisted fallback', () => {
    const store = useListUiStateStore()
    store.setState('team_users', { q: 'persisted', page: 7 })

    setActivePinia(buildTestingPinia())
    const nextStore = useListUiStateStore()
    nextStore.hydrateFromQuery('team_users', { q: 'from-query', page: '2' }, ['q', 'page'])

    expect(nextStore.modules.team_users.q).toBe('from-query')
    expect(nextStore.modules.team_users.page).toBe(2)
    expect(nextStore.toQuery('team_users', ['q', 'page'])).toEqual({
      q: 'from-query',
      page: '2',
    })
  })
})
