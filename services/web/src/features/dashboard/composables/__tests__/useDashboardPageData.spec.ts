import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref, toValue } from 'vue'
import { useDashboardPageData } from '@/features/dashboard/composables/useDashboardPageData'

const {
  authState,
  replaceMock,
  mockUseDashboardSummaryQuery,
  mockUseOrdersQuery,
  mockUseShipmentsQuery,
  mockUseReturnsQuery,
  mockUseInventoryStocksQuery,
} = vi.hoisted(() => ({
  authState: {
    permissions: [] as string[],
  },
  replaceMock: vi.fn(),
  mockUseDashboardSummaryQuery: vi.fn(),
  mockUseOrdersQuery: vi.fn(),
  mockUseShipmentsQuery: vi.fn(),
  mockUseReturnsQuery: vi.fn(),
  mockUseInventoryStocksQuery: vi.fn(),
}))

const createQueryResult = (data: unknown = undefined) => ({
  data: ref(data),
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
  useOrdersQuery: mockUseOrdersQuery,
}))

vi.mock('@/features/shipments/composables/useShipmentsQueries', () => ({
  useShipmentsQuery: mockUseShipmentsQuery,
}))

vi.mock('@/features/returns/composables/useReturnsQueries', () => ({
  useReturnsQuery: mockUseReturnsQuery,
}))

vi.mock('@/features/inventory/composables/useInventoryQueries', () => ({
  useInventoryStocksQuery: mockUseInventoryStocksQuery,
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
    mockUseOrdersQuery.mockReset()
    mockUseShipmentsQuery.mockReset()
    mockUseReturnsQuery.mockReset()
    mockUseInventoryStocksQuery.mockReset()
    mockUseOrdersQuery.mockReturnValue(createQueryResult())
    mockUseShipmentsQuery.mockReturnValue(createQueryResult())
    mockUseReturnsQuery.mockReturnValue(createQueryResult())
    mockUseInventoryStocksQuery.mockReturnValue(createQueryResult())
  })

  const mountHarness = () => {
    let pageData: ReturnType<typeof useDashboardPageData> | undefined

    const Harness = defineComponent({
      setup() {
        pageData = useDashboardPageData()
        return () => h('div')
      },
    })

    mount(Harness)

    return pageData!
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

  it('limits returns-to-restock queue data to the first five results', () => {
    authState.permissions = ['dashboard.view', 'returns.view', 'returns.restock']
    mockUseReturnsQuery.mockReturnValue(
      createQueryResult({
        data: Array.from({ length: 7 }, (_, index) => ({
          id: index + 1,
          order_id: index + 1,
          reason: `Reason ${index + 1}`,
          order: { reference: `OC-2026-0${index + 1}` },
        })),
      }),
    )

    const page = mountHarness()

    expect(page.returnsToRestock.value).toHaveLength(5)
    expect(page.returnsToRestock.value.map((item) => item.id)).toEqual([1, 2, 3, 4, 5])
  })
})
