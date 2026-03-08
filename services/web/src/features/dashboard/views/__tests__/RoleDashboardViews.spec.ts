import { computed, ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import OwnerDashboardView from '@/features/dashboard/views/OwnerDashboardView.vue'
import OrderManagerDashboardView from '@/features/dashboard/views/OrderManagerDashboardView.vue'
import LogisticsDashboardView from '@/features/dashboard/views/LogisticsDashboardView.vue'
import InventoryDashboardView from '@/features/dashboard/views/InventoryDashboardView.vue'
import GenericDashboardView from '@/features/dashboard/views/GenericDashboardView.vue'

const mockUseDashboardPageData = vi.hoisted(() => vi.fn())

vi.mock('@/features/dashboard/composables/useDashboardPageData', () => ({
  useDashboardPageData: mockUseDashboardPageData,
}))

const componentStubs = {
  RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
  Button: { template: '<button><slot /></button>' },
  Card: { template: '<div><slot /></div>' },
  CardHeader: { template: '<div><slot /></div>' },
  CardTitle: { template: '<div><slot /></div>' },
  CardDescription: { template: '<div><slot /></div>' },
  CardContent: { template: '<div><slot /></div>' },
  PageInitialSkeleton: { template: '<div />' },
  PageRefetchOverlay: { template: '<div />' },
  PageHeader: { template: '<div><slot /><slot name="actions" /></div>' },
  DateRangeFilter: { template: '<div />' },
  DashboardQueuesSection: { template: '<div data-test="queues" />' },
  DashboardKpiSection: {
    props: ['cards'],
    template:
      '<div data-test="kpis"><span v-for="card in cards" :key="card.id">{{ card.title }}</span></div>',
  },
  DashboardChartsSection: {
    props: ['charts'],
    template:
      '<div><div data-test="charts">{{ charts.map((card) => card.id).join(",") }}</div><div data-test="companion"><slot name="companion" /></div></div>',
  },
}

const createMockPageData = (overrides?: Record<string, unknown>) => {
  const data = {
    from: ref<string | undefined>(undefined),
    to: ref<string | undefined>(undefined),
    onPreset: vi.fn(),
    updateQuery: vi.fn(),
    dashboardQuery: {
      isLoading: ref(false),
      error: ref(null),
    },
    dashboardData: ref({
      orders: {
        by_status: {
          draft: 3,
          confirmed: 7,
        },
      },
      returns: {
        by_order_status: {
          returned: 6,
          unpaid: 2,
        },
      },
      inventory: {
        movement_in_qty: 9022,
        movement_out_qty: 419,
      },
    }),
    baseKpiCards: ref({
      'orders-total': {
        id: 'orders-total',
        title: 'Orders',
        value: '120',
        description: 'Total orders in selected range',
      },
      'orders-revenue': {
        id: 'orders-revenue',
        title: 'Revenue',
        value: '$16,649.10',
        description: 'Total order revenue',
      },
      'returns-total': {
        id: 'returns-total',
        title: 'Returns',
        value: '9',
        description: 'Return orders in selected range',
      },
      'inventory-low-stock': {
        id: 'inventory-low-stock',
        title: 'Low Stock Alerts',
        value: '5',
        description: 'Products below reorder threshold',
      },
      'inventory-available': {
        id: 'inventory-available',
        title: 'Available Units',
        value: '8,407',
        description: 'Sellable stock units currently available',
      },
    }),
    baseChartCards: ref({
      'orders-by-status': {
        id: 'orders-by-status' as const,
        title: 'Orders by Status',
        description: 'Distribution in selected range',
        points: [],
      },
      'returns-by-outcome': {
        id: 'returns-by-outcome' as const,
        title: 'Returns by Outcome',
        description: 'Returned vs unpaid outcomes',
        points: [],
      },
      'inventory-flow': {
        id: 'inventory-flow' as const,
        title: 'Inventory Flow',
        description: 'Movement in vs movement out',
        points: [],
      },
    }),
    readyOrders: ref([{ id: 1 }, { id: 2 }]),
    returnsToRestock: ref([
      { id: 11, order_id: 11, reason: 'Not as described', order: { reference: 'OC-2026-0112' } },
      { id: 12, order_id: 12, reason: 'Defective product', order: { reference: 'OC-2026-0101' } },
    ]),
    followUpShipments: ref([{ id: 1 }, { id: 2 }, { id: 3 }]),
    lowAvailabilityStocks: ref([
      { product: { id: 101, name: 'Sticker Pack', sku: 'MERCH-STICKER-021' }, available: 17 },
      { product: { id: 102, name: 'Keychain', sku: 'MERCH-KEYCHAIN-018' }, available: 41 },
      { product: { id: 103, name: 'Mousepad', sku: 'MERCH-MOUSEPAD-015' }, available: 59 },
      { product: { id: 104, name: 'Poster', sku: 'MERCH-POSTER-017' }, available: 61 },
    ]),
    queueLoading: ref({
      readyToShip: false,
      returnsToRestock: false,
      shipmentFollowUp: false,
      inventoryAttention: false,
    }),
    queueErrors: ref({
      readyToShip: false,
      returnsToRestock: false,
      shipmentFollowUp: false,
      inventoryAttention: false,
    }),
    isInitialLoading: ref(false),
    isRefetching: ref(false),
    queuePermissions: {
      canViewOrders: computed(() => true),
      canViewShipments: computed(() => true),
      canViewReturns: computed(() => true),
      canViewRestocks: computed(() => true),
      canViewInventory: computed(() => true),
    },
  }

  return {
    ...data,
    ...(overrides ?? {}),
  }
}

describe('Role Dashboard Views', () => {
  it('keeps revenue KPI on owner view', () => {
    mockUseDashboardPageData.mockReturnValue(createMockPageData())
    const wrapper = mount(OwnerDashboardView, {
      global: { stubs: componentStubs },
    })

    expect(wrapper.text()).toContain('Revenue')
  })

  it('removes revenue KPI from order manager and logistics views', () => {
    mockUseDashboardPageData.mockReturnValue(createMockPageData())

    const orderManager = mount(OrderManagerDashboardView, {
      global: { stubs: componentStubs },
    })
    const logistics = mount(LogisticsDashboardView, {
      global: { stubs: componentStubs },
    })

    expect(orderManager.text()).not.toContain('Revenue')
    expect(logistics.text()).not.toContain('Revenue')
  })

  it('renders companion restock panel in chart section for order manager and logistics', () => {
    mockUseDashboardPageData.mockReturnValue(createMockPageData())

    const orderManager = mount(OrderManagerDashboardView, {
      global: { stubs: componentStubs },
    })
    const logistics = mount(LogisticsDashboardView, {
      global: { stubs: componentStubs },
    })

    expect(orderManager.find('[data-test="companion"]').text()).toContain(
      'Recent Restock Candidates',
    )
    expect(logistics.find('[data-test="companion"]').text()).toContain('Recent Restock Candidates')
  })

  it('shows new operational KPI cards for order manager and logistics', () => {
    mockUseDashboardPageData.mockReturnValue(createMockPageData())

    const orderManager = mount(OrderManagerDashboardView, {
      global: { stubs: componentStubs },
    })
    const logistics = mount(LogisticsDashboardView, {
      global: { stubs: componentStubs },
    })

    expect(orderManager.text()).toContain('Draft + Confirmed Backlog')
    expect(orderManager.text()).toContain('Restock Coordination Queue')
    expect(logistics.text()).toContain('Returned Outcomes')
    expect(logistics.text()).toContain('Unpaid Outcomes')
  })

  it('shows data-driven movement metrics and top inventory priorities', () => {
    mockUseDashboardPageData.mockReturnValue(createMockPageData())

    const wrapper = mount(InventoryDashboardView, {
      global: { stubs: componentStubs },
    })

    expect(wrapper.text()).toContain('Movement Visibility')
    expect(wrapper.text()).toContain('9,022')
    expect(wrapper.text()).toContain('419')
    expect(wrapper.text()).toContain('8,603')
    expect(wrapper.text()).toContain('Sticker Pack')
    expect(wrapper.text()).toContain('Review Inventory Stocks')
  })

  it('shows fallback when no low-availability SKUs are present', () => {
    mockUseDashboardPageData.mockReturnValue(
      createMockPageData({
        lowAvailabilityStocks: ref([]),
      }),
    )

    const wrapper = mount(InventoryDashboardView, {
      global: { stubs: componentStubs },
    })

    expect(wrapper.text()).toContain('No low-availability SKUs in the current range.')
  })

  it('removes revenue KPI from generic dashboard', () => {
    mockUseDashboardPageData.mockReturnValue(createMockPageData())

    const wrapper = mount(GenericDashboardView, {
      global: { stubs: componentStubs },
    })

    expect(wrapper.text()).not.toContain('Revenue')
  })
})
