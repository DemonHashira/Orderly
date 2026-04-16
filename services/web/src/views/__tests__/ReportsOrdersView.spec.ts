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
    comparison?: {
      previous_range: {
        from: string | null
        to: string | null
      }
      metrics: Record<
        string,
        {
          current: number | string
          previous: number | string
          delta: number | string
          direction: 'up' | 'down' | 'flat'
          delta_percentage: number | null
        }
      >
    }
    breakdowns: {
      by_channel: Array<{ label: string; value: number }>
      top_products: Array<{
        product_id: number
        name: string
        sku: string
        quantity: number
        revenue: string
      }>
    }
    exceptions: {
      backlog_orders: Array<{
        order_id: number
        reference: string
        status: string
        customer_name: string
        created_at: string
        age_days: number
        total_amount: string
      }>
    }
    actions: Array<{
      id: string
      label: string
      description: string
      to: {
        path: string
        query: Record<string, string>
      }
    }>
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
      comparison: {
        previous_range: {
          from: '2026-02-01',
          to: '2026-02-28',
        },
        metrics: {
          total_orders: {
            current: 24,
            previous: 18,
            delta: 6,
            direction: 'up',
            delta_percentage: 33.3,
          },
          total_revenue: {
            current: '3200.50',
            previous: '2800.00',
            delta: '400.50',
            direction: 'up',
            delta_percentage: 14.3,
          },
          avg_order_value: {
            current: '133.35',
            previous: '155.56',
            delta: '-22.21',
            direction: 'down',
            delta_percentage: -14.3,
          },
        },
      },
      breakdowns: {
        by_channel: [
          { label: 'Retail', value: 14 },
          { label: 'Website', value: 10 },
        ],
        top_products: [
          {
            product_id: 301,
            name: 'Winter Jacket',
            sku: 'JKT-301',
            quantity: 8,
            revenue: '792.00',
          },
        ],
      },
      exceptions: {
        backlog_orders: [
          {
            order_id: 101,
            reference: 'ORD-101',
            status: 'ready_to_ship',
            customer_name: 'Ada Lovelace',
            created_at: '2026-03-20T10:00:00.000000Z',
            age_days: 11,
            total_amount: '420.00',
          },
        ],
      },
      actions: [
        {
          id: 'open-orders-backlog',
          label: 'Open backlog orders',
          description: 'Review draft, confirmed, and ready-to-ship orders.',
          to: {
            path: '/orders',
            query: {
              status: 'ready_to_ship',
              created_from: '2026-03-01',
              created_to: '2026-03-31',
            },
          },
        },
      ],
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
    comparison: {
      previous_range: {
        from: '2026-02-01',
        to: '2026-02-28',
      },
      metrics: {
        total_orders: {
          current: 24,
          previous: 18,
          delta: 6,
          direction: 'up',
          delta_percentage: 33.3,
        },
        total_revenue: {
          current: '3200.50',
          previous: '2800.00',
          delta: '400.50',
          direction: 'up',
          delta_percentage: 14.3,
        },
        avg_order_value: {
          current: '133.35',
          previous: '155.56',
          delta: '-22.21',
          direction: 'down',
          delta_percentage: -14.3,
        },
      },
    },
    breakdowns: {
      by_channel: [
        { label: 'Retail', value: 14 },
        { label: 'Website', value: 10 },
      ],
      top_products: [
        {
          product_id: 301,
          name: 'Winter Jacket',
          sku: 'JKT-301',
          quantity: 8,
          revenue: '792.00',
        },
      ],
    },
    exceptions: {
      backlog_orders: [
        {
          order_id: 101,
          reference: 'ORD-101',
          status: 'ready_to_ship',
          customer_name: 'Ada Lovelace',
          created_at: '2026-03-20T10:00:00.000000Z',
          age_days: 11,
          total_amount: '420.00',
        },
      ],
    },
    actions: [
      {
        id: 'open-orders-backlog',
        label: 'Open backlog orders',
        description: 'Review draft, confirmed, and ready-to-ship orders.',
        to: {
          path: '/orders',
          query: {
            status: 'ready_to_ship',
            created_from: '2026-03-01',
            created_to: '2026-03-31',
          },
        },
      },
    ],
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

  it('renders breakdown and exception content for operator review', async () => {
    const { wrapper } = await mountView()

    await clickTab(wrapper, 'Breakdowns')

    expect(wrapper.text()).toContain('Sales channels')
    expect(wrapper.text()).toContain('Best-selling products')
    expect(wrapper.text()).toContain('Winter Jacket')

    await clickTab(wrapper, 'Exceptions')

    expect(wrapper.text()).toContain('Backlog orders')
    expect(wrapper.text()).toContain('Ada Lovelace')
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
