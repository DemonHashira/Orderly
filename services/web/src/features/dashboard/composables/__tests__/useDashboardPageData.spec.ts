import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref, toValue } from 'vue'
import { useDashboardPageData } from '@/features/dashboard/composables/useDashboardPageData'

const { authState, replaceMock, mockUseDashboardSummaryQuery } = vi.hoisted(() => ({
  authState: {
    permissions: [] as string[],
  },
  replaceMock: vi.fn(),
  mockUseDashboardSummaryQuery: vi.fn(),
}))

const createQueryResult = () => ({
  data: ref(undefined),
  isLoading: ref(false),
  isFetching: ref(false),
  error: ref(null),
})

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ replace: replaceMock }),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: ref(authState.permissions),
  }),
}))

vi.mock('@/features/dashboard/composables/useDashboardSummaryQuery', () => ({
  useDashboardSummaryQuery: mockUseDashboardSummaryQuery,
}))

vi.mock('@/features/orders/composables/useOrdersQueries', () => ({
  useOrdersQuery: () => ({
    data: ref(undefined),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

vi.mock('@/features/shipments/composables/useShipmentsQueries', () => ({
  useShipmentsQuery: () => ({
    data: ref(undefined),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

vi.mock('@/features/returns/composables/useReturnsQueries', () => ({
  useReturnsQuery: () => ({
    data: ref(undefined),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

vi.mock('@/features/inventory/composables/useInventoryQueries', () => ({
  useInventoryStocksQuery: () => ({
    data: ref(undefined),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

vi.mock('@/shared/composables/useInitialLoadingGate', () => ({
  useInitialLoadingGate: () => ref(false),
}))

describe('useDashboardPageData', () => {
  beforeEach(() => {
    authState.permissions = []
    replaceMock.mockReset()
    mockUseDashboardSummaryQuery.mockReset()
    mockUseDashboardSummaryQuery.mockReturnValue(createQueryResult())
  })

  const mountHarness = () => {
    const Harness = defineComponent({
      setup() {
        useDashboardPageData()
        return () => h('div')
      },
    })

    mount(Harness)
  }

  it('disables the dashboard summary query for queue-only dashboard users', () => {
    authState.permissions = ['dashboard.view', 'shipments.view']

    mountHarness()

    expect(mockUseDashboardSummaryQuery).toHaveBeenCalledTimes(1)

    const options = mockUseDashboardSummaryQuery.mock.calls[0]?.[1]
    expect(options).toBeDefined()
    expect(toValue(options.enabled)).toBe(false)
  })

  it('enables the dashboard summary query when a report permission is present', () => {
    authState.permissions = ['dashboard.view', 'reports.orders.view']

    mountHarness()

    const options = mockUseDashboardSummaryQuery.mock.calls[0]?.[1]
    expect(options).toBeDefined()
    expect(toValue(options.enabled)).toBe(true)
  })
})
