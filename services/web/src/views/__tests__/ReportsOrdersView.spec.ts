import { computed, ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import ReportsOrdersView from '@/views/ReportsOrdersView.vue'

type OrdersReportFixture = {
  data: {
    range: {
      from: string | null
      to: string | null
      is_all_time: boolean
    }
    total_orders: number
    total_revenue: string
    avg_order_value: string
    by_status: Record<string, number>
  }
  meta: {
    generated_at: string
  }
}

const ordersState = vi.hoisted(() => ({
  data: {
    data: {
      range: {
        from: '2026-03-01',
        to: '2026-03-31',
        is_all_time: false,
      },
      total_orders: 24,
      total_revenue: '3200.50',
      avg_order_value: '133.35',
      by_status: {
        delivered: 10,
        ready_to_ship: 7,
        cancelled: 2,
      },
    },
    meta: {
      generated_at: '2026-03-31T10:00:00.000000Z',
    },
  } as OrdersReportFixture,
  error: null as unknown,
  isLoading: false,
  isFetching: false,
  permissions: ['reports.orders.view', 'orders.view'] as string[],
}))

const makeOrdersReportFixture = (): OrdersReportFixture => ({
  data: {
    range: {
      from: '2026-03-01',
      to: '2026-03-31',
      is_all_time: false,
    },
    total_orders: 24,
    total_revenue: '3200.50',
    avg_order_value: '133.35',
    by_status: {
      delivered: 10,
      ready_to_ship: 7,
      cancelled: 2,
    },
  },
  meta: {
    generated_at: '2026-03-31T10:00:00.000000Z',
  },
})

const updateQuery = vi.hoisted(() => vi.fn())
const onPreset = vi.hoisted(() => vi.fn())

vi.mock('@/features/reports/composables/useReportDateRangeQuery', () => ({
  useReportDateRangeQuery: () => ({
    from: ref('2026-03-01'),
    to: ref('2026-03-31'),
    updateQuery,
    onPreset,
  }),
}))

vi.mock('@/features/reports/composables/useOrdersReportSummaryQuery', () => ({
  useOrdersReportSummaryQuery: () => ({
    data: computed(() => ordersState.data),
    error: computed(() => ordersState.error),
    isLoading: computed(() => ordersState.isLoading),
    isFetching: computed(() => ordersState.isFetching),
  }),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => ordersState.permissions),
  }),
}))

describe('ReportsOrdersView', () => {
  beforeEach(() => {
    ordersState.data = makeOrdersReportFixture()
    ordersState.error = null
    ordersState.isLoading = false
    ordersState.isFetching = false
    ordersState.permissions = ['reports.orders.view', 'orders.view']
  })

  const mountView = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/reports/orders', component: ReportsOrdersView },
        { path: '/orders', component: { template: '<div />' } },
      ],
    })
    await router.replace('/reports/orders?from=2026-03-01&to=2026-03-31')

    const wrapper = mount(ReportsOrdersView, {
      global: {
        plugins: [router],
      },
    })

    await flushPromises()
    return { wrapper }
  }

  it('renders the report summary and workspace shortcut', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Orders Report')
    expect(wrapper.text()).toContain('24')
    expect(wrapper.text()).toContain('Top status')
    expect(wrapper.text()).toContain('Open Orders Workspace')
    expect(wrapper.text()).toContain('Generated')
  })

  it('shows a zero-activity message for empty but valid summaries', async () => {
    ordersState.data = {
      data: {
        ...makeOrdersReportFixture().data,
        total_orders: 0,
        total_revenue: '0.00',
        avg_order_value: '0.00',
        by_status: {},
      },
      meta: makeOrdersReportFixture().meta,
    }

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('No order activity')
  })

  it('hides generated metadata on initial load errors without cached data', async () => {
    ordersState.data = undefined as unknown as OrdersReportFixture
    ordersState.error = new Error('Orders summary failed')

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Orders report unavailable')
    expect(wrapper.text()).not.toContain('Generated')
  })

  it('keeps stale report data visible during refetch errors', async () => {
    ordersState.error = new Error('Orders summary failed')
    ordersState.isFetching = true

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Orders Report')
    expect(wrapper.text()).toContain('24')
    expect(wrapper.text()).toContain('Generated')
    expect(wrapper.text()).not.toContain('Orders report unavailable')
  })
})
