import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useListUiStateStore } from '../list-ui-state'

describe('useListUiStateStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
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

  it('resets a module back to defaults', () => {
    const store = useListUiStateStore()
    store.setState('shipments', { q: 'track', page: 3, per_page: 30 })
    store.reset('shipments')
    expect(store.modules.shipments.q).toBe('')
    expect(store.modules.shipments.page).toBe(1)
    expect(store.modules.shipments.per_page).toBe(15)
  })
})
