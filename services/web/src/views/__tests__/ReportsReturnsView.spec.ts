import { computed, ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import ReportsReturnsView from '@/views/ReportsReturnsView.vue'

const returnsState = vi.hoisted(() => ({
  data: {
    data: {
      range: {
        from: '2026-03-01',
        to: '2026-03-31',
        is_all_time: false,
      },
      total_returns: 9,
      total_return_items_qty: 22,
      restockable_items_qty: 16,
      non_restockable_items_qty: 6,
      by_order_status: {
        returned: 7,
        unpaid: 2,
      },
    },
    meta: {
      generated_at: '2026-03-31T10:00:00.000000Z',
    },
  },
  error: null as unknown,
  isLoading: false,
  isFetching: false,
  permissions: ['reports.returns.view', 'returns.view', 'inventory.view'] as string[],
}))

const makeReturnsReportFixture = () => ({
  data: {
    range: {
      from: '2026-03-01',
      to: '2026-03-31',
      is_all_time: false,
    },
    total_returns: 9,
    total_return_items_qty: 22,
    restockable_items_qty: 16,
    non_restockable_items_qty: 6,
    by_order_status: {
      returned: 7,
      unpaid: 2,
    },
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

vi.mock('@/features/reports/composables/useReturnsReportSummaryQuery', () => ({
  useReturnsReportSummaryQuery: () => ({
    data: computed(() => returnsState.data),
    error: computed(() => returnsState.error),
    isLoading: computed(() => returnsState.isLoading),
    isFetching: computed(() => returnsState.isFetching),
  }),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => returnsState.permissions),
  }),
}))

describe('ReportsReturnsView', () => {
  beforeEach(() => {
    returnsState.data = makeReturnsReportFixture()
    returnsState.error = null
    returnsState.isLoading = false
    returnsState.isFetching = false
    returnsState.permissions = ['reports.returns.view', 'returns.view', 'inventory.view']
  })

  const mountView = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/reports/returns', component: ReportsReturnsView },
        { path: '/returns', component: { template: '<div />' } },
        { path: '/inventory/stocks', component: { template: '<div />' } },
      ],
    })
    await router.replace('/reports/returns')

    const wrapper = mount(ReportsReturnsView, {
      global: {
        plugins: [router],
      },
    })

    await flushPromises()
    return { wrapper }
  }

  it('renders returns insights and workspace shortcuts', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Returns Report')
    expect(wrapper.text()).toContain('Restock rate')
    expect(wrapper.text()).toContain('Open Returns Workspace')
    expect(wrapper.text()).toContain('Open Inventory Workspace')
  })

  it('hides generated metadata on initial load errors without cached data', async () => {
    returnsState.data = undefined as unknown as typeof returnsState.data
    returnsState.error = new Error('Returns summary failed')

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Returns report unavailable')
    expect(wrapper.text()).not.toContain('Generated')
  })

  it('keeps stale returns data visible during refetch errors', async () => {
    returnsState.error = new Error('Returns summary failed')
    returnsState.isFetching = true

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Returns Report')
    expect(wrapper.text()).toContain('Restock rate')
    expect(wrapper.text()).toContain('Generated')
    expect(wrapper.text()).not.toContain('Returns report unavailable')
  })
})
