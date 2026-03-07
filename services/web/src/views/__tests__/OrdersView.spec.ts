import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import OrdersView from '@/views/OrdersView.vue'

const authState = vi.hoisted(() => ({
  permissions: [] as string[],
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/orders/composables/useOrdersQueries', () => ({
  useOrdersQuery: () => ({
    data: ref({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 15,
      },
    }),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useConfirmOrderMutation: () => ({ mutateAsync: vi.fn() }),
  useReadyToShipOrderMutation: () => ({ mutateAsync: vi.fn() }),
  useCancelOrderMutation: () => ({ mutateAsync: vi.fn() }),
  useDeleteOrderMutation: () => ({ mutateAsync: vi.fn() }),
  useCreateOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useUpdateOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useOrderQuery: () => ({
    data: ref({ data: null }),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

vi.mock('@/features/customers/composables/useCustomersQueries', () => ({
  useCustomersQuery: () => ({
    data: ref({ data: [] }),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

vi.mock('@/features/lookups/composables/useOrderCreateLookupQuery', () => ({
  useOrderCreateLookupQuery: () => ({
    data: ref({ data: { sales_channels: [], products: [] } }),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

describe('OrdersView', () => {
  beforeEach(() => {
    authState.permissions = []
  })

  const mountView = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/orders', component: OrdersView },
        { path: '/orders/new', component: { template: '<div>new order</div>' } },
      ],
    })
    await router.replace('/orders')

    return mount(OrdersView, {
      global: {
        plugins: [createPinia(), router],
        stubs: {
          OrdersDataTable: { template: '<div data-test="orders-table" />' },
          OrderForm: { template: '<div data-test="order-form" />' },
          DateRangeFilter: { template: '<div />' },
          Dialog: { template: '<div><slot /></div>' },
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          DialogDescription: { template: '<div><slot /></div>' },
          PageInitialSkeleton: { template: '<div />' },
          PageRefetchOverlay: { template: '<div />' },
          ApiErrorAlert: { template: '<div />' },
          EmptyStateCard: { template: '<div />' },
        },
      },
    })
  }

  it('renders search and filters heading', async () => {
    const wrapper = await mountView()
    expect(wrapper.text()).toContain('Search & Filters')
  })

  it('shows new order action only when create permission is present', async () => {
    const withoutPermission = await mountView()
    expect(withoutPermission.text()).not.toContain('New Order')

    authState.permissions = ['orders.create']
    const withPermission = await mountView()
    expect(withPermission.text()).toContain('New Order')
  })
})
