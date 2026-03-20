import { computed, ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import ReportsInventoryView from '@/views/ReportsInventoryView.vue'

const inventoryState = vi.hoisted(() => ({
  data: {
    data: {
      range: {
        from: '2026-03-01',
        to: '2026-03-31',
        is_all_time: false,
      },
      total_skus: 44,
      total_on_hand: 950,
      total_reserved: 130,
      total_available: 820,
      low_stock_count: 5,
      movement_in_qty: 70,
      movement_out_qty: 40,
    },
    meta: {
      generated_at: '2026-03-31T10:00:00.000000Z',
    },
  },
  error: null as unknown,
  isLoading: false,
  isFetching: false,
  permissions: ['reports.inventory.view', 'inventory.view'] as string[],
}))

const makeInventoryReportFixture = () => ({
  data: {
    range: {
      from: '2026-03-01',
      to: '2026-03-31',
      is_all_time: false,
    },
    total_skus: 44,
    total_on_hand: 950,
    total_reserved: 130,
    total_available: 820,
    low_stock_count: 5,
    movement_in_qty: 70,
    movement_out_qty: 40,
  },
  meta: {
    generated_at: '2026-03-31T10:00:00.000000Z',
  },
})

vi.mock('@/features/reports/composables/useReportDateRangeQuery', () => ({
  useReportDateRangeQuery: () => ({
    from: ref('2026-03-01'),
    to: ref('2026-03-31'),
    updateQuery: vi.fn(),
    onPreset: vi.fn(),
  }),
}))

vi.mock('@/features/reports/composables/useInventoryReportSummaryQuery', () => ({
  useInventoryReportSummaryQuery: () => ({
    data: computed(() => inventoryState.data),
    error: computed(() => inventoryState.error),
    isLoading: computed(() => inventoryState.isLoading),
    isFetching: computed(() => inventoryState.isFetching),
  }),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => inventoryState.permissions),
  }),
}))

describe('ReportsInventoryView', () => {
  beforeEach(() => {
    inventoryState.data = makeInventoryReportFixture()
    inventoryState.error = null
    inventoryState.isLoading = false
    inventoryState.isFetching = false
    inventoryState.permissions = ['reports.inventory.view', 'inventory.view']
  })

  const mountView = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/reports/inventory', component: ReportsInventoryView },
        { path: '/inventory/stocks', component: { template: '<div />' } },
        { path: '/inventory/movements', component: { template: '<div />' } },
      ],
    })
    await router.replace('/reports/inventory')

    const wrapper = mount(ReportsInventoryView, {
      global: {
        plugins: [router],
      },
    })

    await flushPromises()
    return { wrapper }
  }

  it('renders inventory snapshot and movement copy', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Inventory Report')
    expect(wrapper.text()).toContain('Stock totals are current snapshot values')
    expect(wrapper.text()).toContain('Open Inventory Stocks')
    expect(wrapper.text()).toContain('Open Inventory Movements')
  })

  it('hides generated metadata on initial load errors without cached data', async () => {
    inventoryState.data = undefined as unknown as typeof inventoryState.data
    inventoryState.error = new Error('Inventory summary failed')

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Inventory report unavailable')
    expect(wrapper.text()).not.toContain('Generated')
  })

  it('keeps stale inventory data visible during refetch errors', async () => {
    inventoryState.error = new Error('Inventory summary failed')
    inventoryState.isFetching = true

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Inventory Report')
    expect(wrapper.text()).toContain('Open Inventory Stocks')
    expect(wrapper.text()).toContain('Generated')
    expect(wrapper.text()).not.toContain('Inventory report unavailable')
  })
})
