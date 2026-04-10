import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import OrdersView from '@/views/OrdersView.vue'
import type { Order } from '@/types'

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
      sales_channel_name: 'Online Store',
      created_by: 1,
      current_status: 'ready_to_ship',
      total_amount: '99.00',
      internal_notes: null,
      created_at: '2026-03-07T10:00:00Z',
      updated_at: '2026-03-07T10:00:00Z',
      items: [],
      status_history: [],
    },
  ] as Order[],
  detail: null as Order | null,
}))

const salesChannelsState = vi.hoisted(() => ({
  list: [] as Array<{ id: number; code: string; name: string }>,
}))

const queryOptionsState = vi.hoisted(() => ({
  customersEnabled: [] as boolean[],
  lookupEnabled: [] as boolean[],
}))

const mutationState = vi.hoisted(() => ({
  confirm: vi.fn(),
  createShipment: vi.fn(),
}))

const shipmentsState = vi.hoisted(() => ({
  byOrderId: {} as Record<number, Record<string, unknown>>,
}))

const toastState = vi.hoisted(() => ({
  error: vi.fn(),
  success: vi.fn(),
}))

vi.mock('vue-sonner', () => ({
  toast: {
    error: toastState.error,
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
  useConfirmOrderMutation: () => ({ mutateAsync: mutationState.confirm, isPending: ref(false) }),
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
  useCustomersQuery: (_params: unknown, options?: { enabled?: { value?: boolean } | boolean }) => {
    queryOptionsState.customersEnabled.push(
      typeof options?.enabled === 'object'
        ? Boolean(options.enabled?.value)
        : options?.enabled !== false,
    )

    return {
      data: ref({ data: [] }),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
}))

vi.mock('@/features/lookups/composables/useOrderCreateLookupQuery', () => ({
  useOrderCreateLookupQuery: (enabled?: { value?: boolean } | boolean) => {
    queryOptionsState.lookupEnabled.push(
      typeof enabled === 'object' ? Boolean(enabled?.value) : enabled !== false,
    )

    return {
      data: ref({ data: { sales_channels: [], products: [] } }),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
}))

vi.mock('@/features/sales-channels/composables/useSalesChannelsQuery', () => ({
  useSalesChannelsQuery: () => ({
    data: computed(() => ({ data: salesChannelsState.list })),
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

const OverflowTooltipTextStub = {
  props: ['text', 'dataTest'],
  template: '<p :data-test="dataTest">{{ text }}</p>',
}

const OrdersDataTableStub = {
  props: [
    'rows',
    'currentPage',
    'totalPages',
    'totalRows',
    'perPage',
    'canConfirm',
    'canReadyToShip',
    'canCancel',
    'canEditDraft',
    'canDeleteDraft',
    'canCreateShipment',
  ],
  emits: [
    'confirm',
    'ready-to-ship',
    'cancel',
    'delete',
    'create-shipment',
    'update:page',
    'update:per-page',
  ],
  template: `
    <div>
      <div v-for="row in rows" :key="row.id" data-test="orders-row-sales-channel">
        {{ row.sales_channel_name }}
      </div>
      <div v-for="row in rows" :key="'customer-' + row.id" data-test="orders-row-customer-name">
        {{ row.customer_name }}
      </div>
      <button
        type="button"
        data-test="orders-table-confirm"
        @click="$emit('confirm', 101)"
      >
        Confirm row
      </button>
      <button
        type="button"
        data-test="orders-table-create-shipment"
        @click="$emit('create-shipment', 101)"
      >
        Create shipment from row
      </button>
    </div>
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
        sales_channel_name: 'Online Store',
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
    salesChannelsState.list = []
    queryOptionsState.customersEnabled = []
    queryOptionsState.lookupEnabled = []
    shipmentsState.byOrderId = {}
    mutationState.confirm = vi.fn().mockResolvedValue(undefined)
    mutationState.createShipment = vi.fn().mockResolvedValue({
      data: {
        id: 401,
      },
    })
    toastState.error.mockReset()
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
          DateTimePickerInput: {
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
          OverflowTooltipText: OverflowTooltipTextStub,
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

  it('shows new order action only when create permission is present', async () => {
    const withoutPermission = await mountView()
    expect(withoutPermission.wrapper.text()).not.toContain('New Order')

    authState.permissions = ['orders.create']
    const withPermission = await mountView()
    expect(withPermission.wrapper.text()).toContain('New Order')
  })

  it('does not load order-form support queries on the orders list route', async () => {
    authState.permissions = ['orders.create', 'orders.update']

    await mountView('/orders')

    expect(queryOptionsState.customersEnabled[queryOptionsState.customersEnabled.length - 1]).toBe(
      false,
    )
    expect(queryOptionsState.lookupEnabled[queryOptionsState.lookupEnabled.length - 1]).toBe(false)
  })

  it('loads order-form support queries only when the create route is active', async () => {
    authState.permissions = ['orders.create']

    await mountView('/orders/new')

    expect(queryOptionsState.customersEnabled[queryOptionsState.customersEnabled.length - 1]).toBe(
      true,
    )
    expect(queryOptionsState.lookupEnabled[queryOptionsState.lookupEnabled.length - 1]).toBe(true)
  })

  it('shows detail shipment entry only for eligible status and permission', async () => {
    ordersState.detail = {
      ...ordersState.list[0]!,
      current_status: 'ready_to_ship',
    }

    authState.permissions = ['shipments.create']
    const eligible = await mountView('/orders/101')
    expect(eligible.wrapper.text()).toContain('Create Shipment')

    ordersState.detail = {
      ...ordersState.list[0]!,
      current_status: 'confirmed',
    }
    const blockedStatus = await mountView('/orders/101')
    expect(blockedStatus.wrapper.text()).not.toContain('Create Shipment')

    authState.permissions = []
    ordersState.detail = {
      ...ordersState.list[0]!,
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
    await wrapper.get('#shipment-shipped-at').setValue('2026-03-09T14:45')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(mutationState.createShipment).toHaveBeenCalledTimes(1)
    expect(mutationState.createShipment).toHaveBeenCalledWith({
      orderId: 101,
      payload: expect.objectContaining({
        courier: 'DHL',
        tracking_number: 'TRACK-42',
        shipped_at: new Date(2026, 2, 9, 14, 45).toISOString(),
      }),
    })
  })

  it('closes the confirm dialog and shows a clearer stock conflict toast when confirmation fails', async () => {
    authState.permissions = ['orders.status.confirm']
    mutationState.confirm = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 409,
        data: {
          message: 'Insufficient available stock for product_id=144. Available: 1, Required: 3',
        },
      },
    })

    const { wrapper } = await mountView('/orders')

    await wrapper.get('[data-test="orders-table-confirm"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Confirm order')

    const confirmButton = wrapper
      .findAll('button')
      .find((candidate) => candidate.text().trim() === 'Confirm')
    expect(confirmButton).toBeDefined()

    await confirmButton!.trigger('click')
    await flushPromises()

    expect(mutationState.confirm).toHaveBeenCalledWith(101)
    expect(wrapper.text()).not.toContain('Confirm order')
    expect(toastState.error).toHaveBeenCalledWith(
      'Order could not be confirmed because stock is too low for product #144. Available: 1. Required: 3. Review inventory or reduce the quantity before trying again.',
    )
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
        shipped_at: new Date('2026-03-08T10:30:00Z').toISOString(),
      }),
    })

    expect(toastState.success).toHaveBeenCalledWith('Shipment updated successfully.')
  })

  it('renders sales channel names from order data without create lookups', async () => {
    ordersState.list = [
      {
        ...ordersState.list[0]!,
        sales_channel_id: 8,
        sales_channel_name: 'Retail Store',
      },
    ]

    const { wrapper } = await mountView()

    expect(wrapper.find('[data-test="orders-row-sales-channel"]').text()).toContain('Retail Store')
    expect(wrapper.text()).not.toContain('Channel #8')
  })

  it('renders customer names from order data without customer lookups', async () => {
    ordersState.list = [
      {
        ...ordersState.list[0]!,
        customer_id: 32,
        customer_name: 'Simona Popova',
      },
    ]

    const { wrapper } = await mountView()

    expect(wrapper.find('[data-test="orders-row-customer-name"]').text()).toContain('Simona Popova')
    expect(wrapper.text()).not.toContain('Customer #32')
  })

  it('shows the sales channel name in the detail dialog', async () => {
    ordersState.detail = {
      ...ordersState.list[0]!,
      sales_channel_id: 9,
      sales_channel_name: 'Marketplace',
    }

    const { wrapper } = await mountView('/orders/101')

    expect(wrapper.text()).toContain('Sales Channel:')
    expect(wrapper.text()).toContain('Marketplace')
    expect(wrapper.text()).not.toContain('Sales Channel ID:')
    expect(wrapper.text()).not.toContain('Channel #9')
  })

  it('shows the customer name in the detail dialog', async () => {
    ordersState.detail = {
      ...ordersState.list[0]!,
      customer_id: 24,
      customer_name: 'Yordan Petkov',
    }

    const { wrapper } = await mountView('/orders/101')

    expect(wrapper.text()).toContain('Customer:')
    expect(wrapper.text()).toContain('Yordan Petkov')
    expect(wrapper.text()).not.toContain('Customer ID:')
    expect(wrapper.text()).not.toContain('Customer #24')
  })

  it('shows product names in the detail dialog with metadata instead of a raw product id column', async () => {
    ordersState.detail = {
      ...ordersState.list[0]!,
      items: [
        {
          id: 1,
          product_id: 144,
          quantity: 3,
          unit_price: '68.00',
          total_price: '204.00',
          product: {
            id: 144,
            name: 'Extremely Long Product Name That Should Be Truncated In The Order Detail Table',
            sku: 'PRD-144',
          },
        },
      ],
    }

    const { wrapper } = await mountView('/orders/101')

    expect(wrapper.text()).toContain('Product')
    expect(wrapper.text()).not.toContain('Product ID')
    expect(wrapper.text()).toContain(
      'Extremely Long Product Name That Should Be Truncated In The Order Detail Table',
    )
    expect(wrapper.text()).toContain('ID #144')
    expect(wrapper.text()).toContain('SKU PRD-144')
    expect(wrapper.find('[data-test="orders-detail-tooltip-trigger"]').exists()).toBe(true)
  })
})
