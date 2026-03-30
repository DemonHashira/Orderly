import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import OrdersView from '@/views/OrdersView.vue'

const authState = vi.hoisted(() => ({
  permissions: [] as string[],
}))

const ordersState = vi.hoisted(() => ({
  list: [
    {
      id: 101,
      reference: 'ORD-101',
      customer_id: 12,
      sales_channel_id: 3,
      created_by: 1,
      current_status: 'ready_to_ship',
      total_amount: '99.00',
      internal_notes: null,
      created_at: '2026-03-07T10:00:00Z',
      updated_at: '2026-03-07T10:00:00Z',
      items: [],
      status_history: [],
    },
  ],
  detail: null as Record<string, unknown> | null,
}))

const mutationState = vi.hoisted(() => ({
  createShipment: vi.fn(),
}))

const shipmentsState = vi.hoisted(() => ({
  byOrderId: {} as Record<number, Record<string, unknown>>,
}))

const toastState = vi.hoisted(() => ({
  success: vi.fn(),
}))

vi.mock('vue-sonner', () => ({
  toast: {
    success: toastState.success,
  },
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/orders/composables/useOrdersQueries', () => ({
  useOrdersQuery: () => ({
    data: computed(() => ({
      data: ordersState.list,
      meta: {
        current_page: 1,
        last_page: 1,
        total: ordersState.list.length,
        per_page: 15,
      },
    })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useConfirmOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useReadyToShipOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useCancelOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useDeleteOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useCreateOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useUpdateOrderMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useOrderQuery: () => ({
    data: computed(() => ({ data: ordersState.detail })),
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

vi.mock('@/features/shipments/composables/useShipmentsQueries', () => ({
  useShipmentsQuery: (
    params: { value?: { order_id?: number } } | (() => { order_id?: number }),
  ) => {
    const getParams = () => {
      if (typeof params === 'function') {
        return params()
      }

      return params.value ?? {}
    }

    return {
      data: computed(() => {
        const orderId = Number(getParams().order_id ?? 0)
        const shipment = shipmentsState.byOrderId[orderId]
        return {
          data: shipment ? [shipment] : [],
          meta: {
            current_page: 1,
            last_page: 1,
            total: shipment ? 1 : 0,
            per_page: 1,
          },
        }
      }),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
  useCreateShipmentMutation: () => ({
    mutateAsync: mutationState.createShipment,
    isPending: ref(false),
  }),
}))

const DialogStub = {
  props: ['open'],
  emits: ['update:open'],
  template: '<div v-if="open"><slot /></div>',
}

const OrdersDataTableStub = {
  emits: ['create-shipment', 'update:page', 'update:per-page'],
  template: `
    <button
      type="button"
      data-test="orders-table-create-shipment"
      @click="$emit('create-shipment', 101)"
    >
      Create shipment from row
    </button>
  `,
}

describe('OrdersView', () => {
  beforeEach(() => {
    authState.permissions = []
    ordersState.list = [
      {
        id: 101,
        reference: 'ORD-101',
        customer_id: 12,
        sales_channel_id: 3,
        created_by: 1,
        current_status: 'ready_to_ship',
        total_amount: '99.00',
        internal_notes: null,
        created_at: '2026-03-07T10:00:00Z',
        updated_at: '2026-03-07T10:00:00Z',
        items: [],
        status_history: [],
      },
    ]
    ordersState.detail = null
    shipmentsState.byOrderId = {}
    mutationState.createShipment = vi.fn().mockResolvedValue({
      data: {
        id: 401,
      },
    })
    toastState.success.mockReset()
  })

  const mountView = async (path = '/orders') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/orders', name: 'orders', component: OrdersView },
        { path: '/orders/new', name: 'order-create', component: OrdersView },
        { path: '/orders/:id', name: 'order-detail', component: OrdersView },
        { path: '/orders/:id/edit', name: 'order-edit', component: OrdersView },
      ],
    })
    await router.replace(path)

    const wrapper = mount(OrdersView, {
      global: {
        plugins: [createPinia(), router],
        stubs: {
          OrdersDataTable: OrdersDataTableStub,
          OrderForm: { template: '<div data-test="order-form" />' },
          DateRangeFilter: { template: '<div />' },
          CourierComboboxInput: {
            props: ['modelValue', 'inputId', 'dataTest'],
            emits: ['update:modelValue'],
            template:
              '<input :id="inputId" :data-test="dataTest" :value="modelValue" @input="$emit(`update:modelValue`, $event.target.value)" />',
          },
          DatePickerInput: {
            props: ['modelValue', 'triggerId', 'dataTest'],
            emits: ['update:modelValue'],
            template:
              '<input :id="triggerId" :data-test="dataTest" :value="modelValue" @input="$emit(`update:modelValue`, $event.target.value)" />',
          },
          Dialog: DialogStub,
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          DialogDescription: { template: '<div><slot /></div>' },
          AlertDialog: DialogStub,
          AlertDialogContent: { template: '<div><slot /></div>' },
          AlertDialogHeader: { template: '<div><slot /></div>' },
          AlertDialogTitle: { template: '<div><slot /></div>' },
          AlertDialogDescription: { template: '<div><slot /></div>' },
          AlertDialogFooter: { template: '<div><slot /></div>' },
          AlertDialogCancel: { template: '<button type="button"><slot /></button>' },
          PageInitialSkeleton: { template: '<div />' },
          PageRefetchOverlay: { template: '<div />' },
          ApiErrorAlert: { template: '<div />' },
          EmptyStateCard: { template: '<div />' },
        },
      },
    })

    await flushPromises()
    return { wrapper, router }
  }

  it('renders search and filters heading', async () => {
    const { wrapper } = await mountView()
    expect(wrapper.text()).toContain('Search & Filters')
  })

  it('shows new order action only when create permission is present', async () => {
    const withoutPermission = await mountView()
    expect(withoutPermission.wrapper.text()).not.toContain('New Order')

    authState.permissions = ['orders.create']
    const withPermission = await mountView()
    expect(withPermission.wrapper.text()).toContain('New Order')
    expect(withPermission.wrapper.find('[data-test="orders-open-create"] svg').exists()).toBe(true)
  })

  it('shows detail shipment entry only for eligible status and permission', async () => {
    ordersState.detail = {
      ...ordersState.list[0],
      current_status: 'ready_to_ship',
    }

    authState.permissions = ['shipments.create']
    const eligible = await mountView('/orders/101')
    expect(eligible.wrapper.text()).toContain('Create Shipment')

    ordersState.detail = {
      ...ordersState.list[0],
      current_status: 'confirmed',
    }
    const blockedStatus = await mountView('/orders/101')
    expect(blockedStatus.wrapper.text()).not.toContain('Create Shipment')

    authState.permissions = []
    ordersState.detail = {
      ...ordersState.list[0],
      current_status: 'ready_to_ship',
    }
    const blockedPermission = await mountView('/orders/101')
    expect(blockedPermission.wrapper.text()).not.toContain('Create Shipment')
  })

  it('shows a loading body when detail dialog opens before order data is ready', async () => {
    ordersState.detail = null

    const { wrapper } = await mountView('/orders/101')

    expect(wrapper.text()).toContain('Loading order...')
  })

  it('validates and submits shipment payload from orders row action', async () => {
    authState.permissions = ['shipments.create']
    const { wrapper } = await mountView('/orders')

    await wrapper.get('[data-test="orders-table-create-shipment"]').trigger('click')
    await flushPromises()

    await wrapper.get('form').trigger('submit.prevent')
    expect(wrapper.text()).toContain('Courier is required.')
    expect(mutationState.createShipment).not.toHaveBeenCalled()

    await wrapper.get('#shipment-courier').setValue('  DHL  ')
    await wrapper.get('#shipment-tracking').setValue(' TRACK-42 ')
    await wrapper.get('#shipment-shipped-at').setValue('2026-03-09')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(mutationState.createShipment).toHaveBeenCalledTimes(1)
    expect(mutationState.createShipment).toHaveBeenCalledWith({
      orderId: 101,
      payload: expect.objectContaining({
        courier: 'DHL',
        tracking_number: 'TRACK-42',
        shipped_at: '2026-03-09',
      }),
    })
  })

  it('preserves existing tracking and shipped date during shipment updates', async () => {
    authState.permissions = ['shipments.create']
    ordersState.list = [
      {
        ...ordersState.list[0]!,
        current_status: 'shipped',
      },
    ]
    shipmentsState.byOrderId = {
      101: {
        id: 501,
        order_id: 101,
        courier: 'Speedy',
        tracking_number: 'TRK-EXISTING',
        shipped_at: '2026-03-08T10:30:00Z',
        delivered_at: null,
        created_at: '2026-03-08T10:30:00Z',
        updated_at: '2026-03-08T10:30:00Z',
      },
    }

    const { wrapper } = await mountView('/orders')
    await wrapper.get('[data-test="orders-table-create-shipment"]').trigger('click')
    await flushPromises()

    await wrapper.get('#shipment-courier').setValue('Econt')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(mutationState.createShipment).toHaveBeenCalledTimes(1)
    expect(mutationState.createShipment).toHaveBeenCalledWith({
      orderId: 101,
      payload: expect.objectContaining({
        courier: 'Econt',
        tracking_number: 'TRK-EXISTING',
        shipped_at: '2026-03-08',
      }),
    })
  })

  it('shows an updated toast when editing an existing shipment', async () => {
    authState.permissions = ['shipments.create']
    ordersState.list = [
      {
        ...ordersState.list[0]!,
        current_status: 'shipped',
      },
    ]
    shipmentsState.byOrderId = {
      101: {
        id: 501,
        order_id: 101,
        courier: 'Speedy',
        tracking_number: 'TRK-EXISTING',
        shipped_at: '2026-03-08T10:30:00Z',
        delivered_at: null,
        created_at: '2026-03-08T10:30:00Z',
        updated_at: '2026-03-08T10:30:00Z',
      },
    }

    const { wrapper } = await mountView('/orders')
    await wrapper.get('[data-test="orders-table-create-shipment"]').trigger('click')
    await flushPromises()

    await wrapper.get('#shipment-courier').setValue('Econt')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(toastState.success).toHaveBeenCalledWith('Shipment updated successfully.')
  })
})
