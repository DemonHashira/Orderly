import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import InventoryStocksView from '@/views/InventoryStocksView.vue'
import { useListUiStateStore } from '@/stores/list-ui-state'

const authState = vi.hoisted(() => ({
  permissions: [] as string[],
}))

const inventoryState = vi.hoisted(() => ({
  stocks: [
    {
      product: {
        id: 301,
        name: 'Winter Jacket',
        sku: 'JKT-301',
        is_active: true,
      },
      qty_on_hand: 20,
      qty_reserved: 4,
      available: 16,
    },
  ],
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/inventory/composables/useInventoryQueries', () => ({
  useInventoryStocksQuery: () => ({
    data: computed(() => ({
      data: inventoryState.stocks,
      meta: {
        current_page: 1,
        last_page: 3,
        total: inventoryState.stocks.length,
        per_page: 15,
      },
    })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

const InventoryStocksDataTableStub = {
  emits: ['open-movements', 'update:page', 'update:per-page'],
  template: `
    <div>
      <button type="button" data-test="stocks-open-movements" @click="$emit('open-movements', 301)">
        Open movements
      </button>
      <button type="button" data-test="stocks-per-page" @click="$emit('update:per-page', 25)">
        Per page
      </button>
    </div>
  `,
}

describe('InventoryStocksView', () => {
  beforeEach(() => {
    authState.permissions = []
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const mountView = async (path = '/inventory/stocks') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/inventory/stocks', name: 'inventory-stocks', component: InventoryStocksView },
        {
          path: '/inventory/movements',
          name: 'inventory-movements',
          component: { template: '<div />' },
        },
      ],
    })
    await router.replace(path)

    const pinia = createPinia()

    const wrapper = mount(InventoryStocksView, {
      global: {
        plugins: [pinia, router],
        stubs: {
          InventoryStocksDataTable: InventoryStocksDataTableStub,
          PageHeader: { template: '<div><slot name="actions" /></div>' },
          PageInitialSkeleton: { template: '<div />' },
          PageRefetchOverlay: { template: '<div />' },
          ApiErrorAlert: { template: '<div />' },
          EmptyStateCard: { template: '<div />' },
          Select: { template: '<div><slot /></div>' },
          SelectTrigger: { template: '<div><slot /></div>' },
          SelectValue: { template: '<div><slot /></div>' },
          SelectContent: { template: '<div><slot /></div>' },
          SelectItem: { template: '<div><slot /></div>' },
        },
      },
    })

    await flushPromises()
    return { wrapper, router, pinia }
  }

  it('syncs stock search input to route query', async () => {
    vi.useFakeTimers()
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="inventory-stocks-search"]').setValue('jacket')
    await vi.advanceTimersByTimeAsync(320)
    await flushPromises()

    expect(router.currentRoute.value.query.q).toBe('jacket')
  })

  it('hydrates stock filters from route query', async () => {
    const { pinia } = await mountView('/inventory/stocks?q=coat&status=archived&page=2')
    const listUiStore = useListUiStateStore(pinia)

    expect(listUiStore.modules.inventory_stocks.q).toBe('coat')
    expect(listUiStore.modules.inventory_stocks.status).toBe('archived')
    expect(listUiStore.modules.inventory_stocks.page).toBe(2)
  })

  it('resets stock filters back to defaults', async () => {
    const { wrapper, router, pinia } = await mountView(
      '/inventory/stocks?q=coat&status=archived&page=2',
    )
    const listUiStore = useListUiStateStore(pinia)

    const resetButton = wrapper.findAll('button').find((button) => button.text() === 'Reset')
    expect(resetButton).toBeTruthy()
    await resetButton!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({})
    expect(listUiStore.modules.inventory_stocks.q).toBe('')
    expect(listUiStore.modules.inventory_stocks.status).toBe('')
    expect(listUiStore.modules.inventory_stocks.page).toBe(1)
  })

  it('opens movement history from the stock table', async () => {
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="stocks-open-movements"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/inventory/movements')
    expect(router.currentRoute.value.query.product_id).toBe('301')
  })

  it('shows the record movement shortcut only with inventory.movement.create permission', async () => {
    const withoutPermission = await mountView()
    expect(
      withoutPermission.wrapper.find('[data-test="inventory-stocks-open-movements"]').exists(),
    ).toBe(false)

    authState.permissions = ['inventory.movement.create']
    const withPermission = await mountView()
    expect(
      withPermission.wrapper.find('[data-test="inventory-stocks-open-movements"]').exists(),
    ).toBe(true)
  })

  it('routes the header shortcut to the movements workspace', async () => {
    authState.permissions = ['inventory.movement.create']
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="inventory-stocks-open-movements"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/inventory/movements')
  })

  it('syncs per-page changes to route query', async () => {
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="stocks-per-page"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.per_page).toBe('25')
  })
})
