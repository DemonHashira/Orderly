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
      comparison: {
        previous_range: {
          from: '2026-02-01',
          to: '2026-02-28',
        },
        metrics: {
          total_available: {
            current: 820,
            previous: 760,
            delta: 60,
            direction: 'up',
            delta_percentage: 7.9,
          },
        },
      },
      breakdowns: {
        by_movement_type: [
          { label: 'Restock', value: 70 },
          { label: 'Damage', value: 12 },
        ],
        by_reference_type: [
          { label: 'Order', value: 40 },
          { label: 'Return', value: 18 },
        ],
      },
      exceptions: {
        attention_items: [
          {
            product_id: 401,
            name: 'Archive Hoodie',
            sku: 'HD-401',
            status: 'low_stock',
            qty_on_hand: 3,
            qty_reserved: 2,
            qty_available: 1,
            reorder_threshold: 5,
            shortage_qty: 2,
          },
        ],
      },
      actions: [
        {
          id: 'open-low-stock-items',
          label: 'Open low stock items',
          description: 'Review items that need replenishment.',
          to: {
            path: '/inventory/stocks',
            query: {
              q: 'HD-401',
            },
          },
        },
      ],
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
    comparison: {
      previous_range: {
        from: '2026-02-01',
        to: '2026-02-28',
      },
      metrics: {
        total_available: {
          current: 820,
          previous: 760,
          delta: 60,
          direction: 'up',
          delta_percentage: 7.9,
        },
      },
    },
    breakdowns: {
      by_movement_type: [
        { label: 'Restock', value: 70 },
        { label: 'Damage', value: 12 },
      ],
      by_reference_type: [
        { label: 'Order', value: 40 },
        { label: 'Return', value: 18 },
      ],
    },
    exceptions: {
      attention_items: [
        {
          product_id: 401,
          name: 'Archive Hoodie',
          sku: 'HD-401',
          status: 'low_stock',
          qty_on_hand: 3,
          qty_reserved: 2,
          qty_available: 1,
          reorder_threshold: 5,
          shortage_qty: 2,
        },
      ],
    },
    actions: [
      {
        id: 'open-low-stock-items',
        label: 'Open low stock items',
        description: 'Review items that need replenishment.',
        to: {
          path: '/inventory/stocks',
          query: {
            q: 'HD-401',
          },
        },
      },
    ],
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

  const clickTab = async (
    wrapper: Awaited<ReturnType<typeof mountView>>['wrapper'],
    label: string,
  ) => {
    const trigger = wrapper
      .findAll('[data-slot="tabs-trigger"]')
      .find((candidate) => candidate.text() === label)

    expect(trigger).toBeTruthy()
    await trigger!.trigger('click')
    await flushPromises()
  }

  it('renders inventory snapshot and movement copy', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Inventory Report')
    expect(wrapper.text()).toContain('Overview')
    expect(wrapper.text()).toContain('Exceptions')
    expect(wrapper.text()).toContain('Breakdowns')
    expect(wrapper.text()).toContain('Compare to previous period')
    expect(wrapper.text()).toContain('Open low stock items')
  })

  it('renders inventory attention and breakdown sections', async () => {
    const { wrapper } = await mountView()

    await clickTab(wrapper, 'Exceptions')

    expect(wrapper.text()).toContain('Inventory attention')
    expect(wrapper.text()).toContain('Archive Hoodie')

    await clickTab(wrapper, 'Breakdowns')

    expect(wrapper.text()).toContain('Movement type')
    expect(wrapper.text()).toContain('Reference source')
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
    expect(wrapper.text()).toContain('Open low stock items')
    expect(wrapper.text()).toContain('Generated')
    expect(wrapper.text()).not.toContain('Inventory report unavailable')
  })
})
