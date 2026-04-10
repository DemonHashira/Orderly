import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref, toValue } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import ShipmentsView from '@/views/ShipmentsView.vue'

const authState = vi.hoisted(() => ({
  permissions: [] as string[],
}))

const shipmentsState = vi.hoisted(() => ({
  list: [
    {
      id: 11,
      order_id: 101,
      courier: 'DHL',
      tracking_number: 'TRK-11',
      shipped_at: '2026-03-09T08:00:00Z',
      delivered_at: null,
      created_at: '2026-03-09T08:00:00Z',
      updated_at: '2026-03-09T08:00:00Z',
      order: {
        id: 101,
        reference: 'ORD-101',
        current_status: 'shipped',
      },
    },
  ],
}))

const mutationState = vi.hoisted(() => ({
  delivered: vi.fn(),
  returned: vi.fn(),
  unpaid: vi.fn(),
}))

const shipmentDetailState = vi.hoisted(() => ({
  detail: null as Record<string, unknown> | null,
}))

const shipmentsQueryCapture = vi.hoisted(() => ({
  lastParams: null as unknown,
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/shipments/composables/useShipmentsQueries', () => ({
  useShipmentsQuery: (params: unknown) => {
    shipmentsQueryCapture.lastParams = computed(() => toValue(params) as Record<string, unknown>)

    return {
      data: computed(() => ({
        data: shipmentsState.list,
        meta: {
          current_page: 1,
          last_page: 1,
          total: shipmentsState.list.length,
          per_page: 15,
        },
      })),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
  useShipmentQuery: () => ({
    data: computed(() => ({ data: shipmentDetailState.detail })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useMarkShipmentDeliveredMutation: () => ({
    mutateAsync: mutationState.delivered,
    isPending: ref(false),
  }),
  useMarkShipmentReturnedMutation: () => ({
    mutateAsync: mutationState.returned,
    isPending: ref(false),
  }),
  useMarkShipmentUnpaidMutation: () => ({
    mutateAsync: mutationState.unpaid,
    isPending: ref(false),
  }),
}))

const DialogStub = {
  props: ['open'],
  emits: ['update:open'],
  template: '<div v-if="open"><slot /></div>',
}

const DropdownItemStub = {
  emits: ['select'],
  template: '<button type="button" @click="$emit(`select`)"><slot /></button>',
}

describe('ShipmentsView', () => {
  beforeEach(() => {
    authState.permissions = []
    shipmentDetailState.detail = null
    shipmentsQueryCapture.lastParams = null
    mutationState.delivered = vi.fn().mockResolvedValue({})
    mutationState.returned = vi.fn().mockResolvedValue({})
    mutationState.unpaid = vi.fn().mockResolvedValue({})
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const mountView = async (path = '/shipments') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/shipments', name: 'shipments', component: ShipmentsView },
        { path: '/orders', name: 'orders', component: { template: '<div />' } },
        { path: '/shipments/:id', name: 'shipment-detail', component: { template: '<div />' } },
      ],
    })
    await router.replace(path)

    const wrapper = mount(ShipmentsView, {
      global: {
        plugins: [createPinia(), router],
        stubs: {
          DateRangeFilter: { template: '<div />' },
          PageHeader: { template: '<div><slot name="actions" /></div>' },
          PageInitialSkeleton: { template: '<div />' },
          PageRefetchOverlay: { template: '<div />' },
          ApiErrorAlert: { template: '<div />' },
          EmptyStateCard: { template: '<div />' },
          ServerPagination: { template: '<div />' },
          StatusBadge: { template: '<span />' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuLabel: { template: '<div><slot /></div>' },
          DropdownMenuSeparator: { template: '<div />' },
          DropdownMenuItem: DropdownItemStub,
          Select: { template: '<div><slot /></div>' },
          SelectTrigger: { template: '<div><slot /></div>' },
          SelectValue: { template: '<div><slot /></div>' },
          SelectContent: { template: '<div><slot /></div>' },
          SelectItem: { template: '<div><slot /></div>' },
          AlertDialog: DialogStub,
          AlertDialogContent: { template: '<div><slot /></div>' },
          AlertDialogHeader: { template: '<div><slot /></div>' },
          AlertDialogTitle: { template: '<div><slot /></div>' },
          AlertDialogDescription: { template: '<div><slot /></div>' },
          AlertDialogFooter: { template: '<div><slot /></div>' },
          AlertDialogCancel: { template: '<button type="button"><slot /></button>' },
          Dialog: DialogStub,
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          DialogDescription: { template: '<div><slot /></div>' },
        },
      },
    })

    await flushPromises()
    return { wrapper, router }
  }

  it('shows only permitted lifecycle actions in row menu', async () => {
    authState.permissions = ['shipments.outcome.delivered']
    const deliveredOnly = await mountView()

    expect(deliveredOnly.wrapper.find('[data-test="shipments-mark-delivered-11"]').exists()).toBe(
      true,
    )
    expect(deliveredOnly.wrapper.find('[data-test="shipments-mark-returned-11"]').exists()).toBe(
      false,
    )
    expect(deliveredOnly.wrapper.find('[data-test="shipments-mark-unpaid-11"]').exists()).toBe(
      false,
    )

    authState.permissions = [
      'shipments.outcome.delivered',
      'shipments.outcome.returned',
      'shipments.outcome.unpaid',
    ]
    const allActions = await mountView()
    expect(allActions.wrapper.find('[data-test="shipments-mark-delivered-11"]').exists()).toBe(true)
    expect(allActions.wrapper.find('[data-test="shipments-mark-returned-11"]').exists()).toBe(true)
    expect(allActions.wrapper.find('[data-test="shipments-mark-unpaid-11"]').exists()).toBe(true)
  })

  it('shows the ready orders shortcut only with orders.view permission', async () => {
    const withoutPermission = await mountView()
    expect(
      withoutPermission.wrapper.find('[data-test="shipments-open-ready-orders"]').exists(),
    ).toBe(false)

    authState.permissions = ['orders.view']
    const withPermission = await mountView()
    expect(withPermission.wrapper.find('[data-test="shipments-open-ready-orders"]').exists()).toBe(
      true,
    )
  })

  it('routes the header shortcut to ready-to-ship orders', async () => {
    authState.permissions = ['orders.view']
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="shipments-open-ready-orders"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/orders')
    expect(router.currentRoute.value.query.status).toBe('ready_to_ship')
  })

  it('runs delivered mutation after confirmation', async () => {
    authState.permissions = ['shipments.outcome.delivered']
    const { wrapper } = await mountView()

    await wrapper.get('[data-test="shipments-mark-delivered-11"]').trigger('click')
    await flushPromises()

    const confirmButton = wrapper
      .findAll('button')
      .find((candidate) => candidate.text().trim() === 'Delivered')
    expect(confirmButton).toBeDefined()

    await confirmButton!.trigger('click')
    await flushPromises()

    expect(mutationState.delivered).toHaveBeenCalledWith(11)
  })

  it('syncs search and courier filters to route query', async () => {
    vi.useFakeTimers()
    const { wrapper, router } = await mountView('/shipments')

    await wrapper.get('[data-test="shipments-search"]').setValue('TRK-99')
    await wrapper.get('[data-test="shipments-courier-filter"]').setValue('Econt')
    await vi.advanceTimersByTimeAsync(320)
    await flushPromises()

    expect(router.currentRoute.value.query.q).toBe('TRK-99')
    expect(router.currentRoute.value.query.courier).toBe('Econt')
  })

  it('hydrates search and courier filters from route query', async () => {
    const { wrapper } = await mountView('/shipments?q=ABC123&courier=Speedy')

    const search = wrapper.get('[data-test="shipments-search"]').element as HTMLInputElement
    const courier = wrapper.get('[data-test="shipments-courier-filter"]')
      .element as HTMLInputElement

    expect(search.value).toBe('ABC123')
    expect(courier.value).toBe('Speedy')
  })

  it('hydrates outcome filter from route query into shipments params', async () => {
    await mountView('/shipments?status=returned')

    expect(
      (toValue(shipmentsQueryCapture.lastParams as object) as Record<string, unknown>).outcome,
    ).toBe('returned')
  })

  it('opens shipment detail inside dialog route context', async () => {
    shipmentDetailState.detail = {
      ...shipmentsState.list[0],
      id: 11,
    }

    const { wrapper } = await mountView('/shipments/11')
    expect(wrapper.text()).toContain('Shipment Detail')
    expect(wrapper.text()).toContain('ORD-101')
  })
})
