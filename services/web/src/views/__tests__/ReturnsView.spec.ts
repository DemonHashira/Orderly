import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import ReturnsView from '@/views/ReturnsView.vue'
import { useListUiStateStore } from '@/stores/list-ui-state'

const authState = vi.hoisted(() => ({
  permissions: [] as string[],
}))

const returnsState = vi.hoisted(() => ({
  list: [
    {
      id: 11,
      order_id: 101,
      reason: 'Damaged package',
      returned_at: '2026-03-09T08:00:00Z',
      restocked_at: null,
      created_at: '2026-03-09T08:00:00Z',
      updated_at: '2026-03-09T08:00:00Z',
      order: {
        id: 101,
        reference: 'ORD-101',
        current_status: 'returned',
        customer_id: 12,
        items: [
          {
            id: 801,
            product_id: 301,
            quantity: 2,
            product: {
              id: 301,
              name: 'Winter Jacket',
              sku: 'JKT-301',
            },
          },
        ],
      },
      items: [
        {
          id: 701,
          product_id: 301,
          quantity: 1,
          restockable: true,
          product: {
            id: 301,
            name: 'Winter Jacket',
            sku: 'JKT-301',
          },
        },
      ],
    },
  ],
  detail: null as Record<string, unknown> | null,
}))

const mutationState = vi.hoisted(() => ({
  restock: vi.fn(),
  addItem: vi.fn(),
}))

const customerState = vi.hoisted(() => ({
  detail: {
    id: 12,
    name: 'Mina Petrova',
    first_name: 'Mina',
    middle_name: null,
    last_name: 'Petrova',
    email: 'mina@example.com',
    phone: '+359888000111',
  },
}))

const customerQueryCalls = vi.hoisted(() => [] as number[])

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/returns/composables/useReturnsQueries', () => ({
  useReturnsQuery: () => ({
    data: computed(() => ({
      data: returnsState.list,
      meta: {
        current_page: 1,
        last_page: 1,
        total: returnsState.list.length,
        per_page: 15,
      },
    })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useReturnQuery: () => ({
    data: computed(() => ({ data: returnsState.detail })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useRestockReturnMutation: () => ({
    mutateAsync: mutationState.restock,
    isPending: ref(false),
  }),
  useAddReturnItemMutation: () => ({
    mutateAsync: mutationState.addItem,
    isPending: ref(false),
  }),
}))

vi.mock('@/features/orders/composables/useOrdersQueries', () => ({
  useOrderQuery: () => {
    throw new Error('ReturnsView should not query order details directly')
  },
}))

vi.mock('@/features/customers/composables/useCustomersQueries', () => ({
  useCustomerQuery: (id: { value?: number } | number) => {
    customerQueryCalls.push(typeof id === 'number' ? id : (id.value ?? 0))

    return {
      data: computed(() => ({ data: customerState.detail })),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
}))

vi.mock('@/features/lookups/composables/useOrderCreateLookupQuery', () => ({
  useOrderCreateLookupQuery: () => {
    throw new Error('ReturnsView should not use order-create lookups for return items')
  },
}))

const DialogStub = {
  props: ['open'],
  emits: ['update:open'],
  template: '<div v-if="open"><slot /></div>',
}

const ReturnsDataTableStub = {
  props: ['canRestock'],
  emits: ['restock', 'update:page', 'update:per-page'],
  template: `
    <button
      v-if="canRestock"
      type="button"
      data-test="returns-table-restock"
      @click="$emit('restock', 11)"
    >
      Restock row
    </button>
  `,
}

describe('ReturnsView', () => {
  beforeEach(() => {
    authState.permissions = ['returns.view']
    customerQueryCalls.length = 0
    returnsState.detail = {
      ...returnsState.list[0],
      id: 11,
    }

    mutationState.restock = vi.fn().mockResolvedValue({
      data: returnsState.list[0],
    })
    mutationState.addItem = vi.fn().mockResolvedValue({
      data: returnsState.list[0],
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const mountView = async (path = '/returns') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/returns', name: 'returns', component: ReturnsView },
        { path: '/returns/:id', name: 'return-detail', component: ReturnsView },
      ],
    })
    await router.replace(path)

    const pinia = createPinia()

    const wrapper = mount(ReturnsView, {
      global: {
        plugins: [pinia, router],
        stubs: {
          ReturnsDataTable: ReturnsDataTableStub,
          DateRangeFilter: { template: '<div />' },
          PageHeader: { template: '<div><slot name="actions" /></div>' },
          PageInitialSkeleton: { template: '<div />' },
          PageRefetchOverlay: { template: '<div />' },
          ApiErrorAlert: { template: '<div><slot /></div>' },
          EmptyStateCard: { template: '<div />' },
          StatusBadge: { template: '<span><slot /></span>' },
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
          Select: { template: '<div><slot /></div>' },
          SelectTrigger: { template: '<div><slot /></div>' },
          SelectValue: { template: '<div><slot /></div>' },
          SelectContent: { template: '<div><slot /></div>' },
          SelectItem: { template: '<div><slot /></div>' },
        },
      },
    })

    await flushPromises()
    return { wrapper, router, pinia }
  }

  it('syncs search input to route query', async () => {
    vi.useFakeTimers()
    const { wrapper, router } = await mountView('/returns')

    await wrapper.get('[data-test="returns-search"]').setValue('damaged')
    await vi.advanceTimersByTimeAsync(320)
    await flushPromises()

    expect(router.currentRoute.value.query.q).toBe('damaged')
  })

  it('hydrates returns list state from route query', async () => {
    const { pinia } = await mountView('/returns?q=wrong-item&status=restockable&page=2')
    const listUiStore = useListUiStateStore(pinia)

    expect(listUiStore.modules.returns.q).toBe('wrong-item')
    expect(listUiStore.modules.returns.status).toBe('restockable')
    expect(listUiStore.modules.returns.page).toBe(2)
  })

  it('hydrates legacy has_restockable query into the restockable filter', async () => {
    const { pinia } = await mountView('/returns?has_restockable=true')
    const listUiStore = useListUiStateStore(pinia)

    expect(listUiStore.modules.returns.status).toBe('restockable')
  })

  it('shows restock row action only when permission is present', async () => {
    authState.permissions = ['returns.view']
    const withoutPermission = await mountView('/returns')
    expect(withoutPermission.wrapper.find('[data-test="returns-table-restock"]').exists()).toBe(
      false,
    )

    authState.permissions = ['returns.view', 'returns.restock']
    const withPermission = await mountView('/returns')
    expect(withPermission.wrapper.find('[data-test="returns-table-restock"]').exists()).toBe(true)
  })

  it('shows the restock queue shortcut for return viewers', async () => {
    authState.permissions = []
    const withoutPermission = await mountView('/returns')
    expect(
      withoutPermission.wrapper.find('[data-test="returns-open-restock-queue"]').exists(),
    ).toBe(false)

    authState.permissions = ['returns.view']
    const withPermission = await mountView('/returns')
    expect(withPermission.wrapper.find('[data-test="returns-open-restock-queue"]').exists()).toBe(
      true,
    )
  })

  it('applies the restockable queue filter from the header shortcut', async () => {
    const { wrapper, router, pinia } = await mountView('/returns')
    const listUiStore = useListUiStateStore(pinia)

    await wrapper.get('[data-test="returns-open-restock-queue"]').trigger('click')
    await flushPromises()

    expect(listUiStore.modules.returns.status).toBe('restockable')
    expect(router.currentRoute.value.query.status).toBe('restockable')
  })

  it('runs restock mutation after confirmation', async () => {
    authState.permissions = ['returns.view', 'returns.restock']
    const { wrapper } = await mountView('/returns')

    await wrapper.get('[data-test="returns-table-restock"]').trigger('click')
    await flushPromises()

    await wrapper.get('[data-test="returns-confirm-action"]').trigger('click')
    await flushPromises()

    expect(mutationState.restock).toHaveBeenCalledWith(11)
  })

  it('opens return detail in dialog route context', async () => {
    const { wrapper } = await mountView('/returns/11')

    expect(wrapper.text()).toContain('Return Detail')
    expect(wrapper.text()).toContain('ORD-101')
  })

  it('hides the detail restock action when the return is already restocked', async () => {
    authState.permissions = ['returns.view', 'returns.restock']
    returnsState.detail = {
      ...returnsState.list[0],
      id: 11,
      restocked_at: '2026-03-10T08:00:00Z',
    }

    const { wrapper } = await mountView('/returns/11')

    expect(wrapper.find('[data-test="return-detail-restock"]').exists()).toBe(false)
  })

  it('shows linked customer details in return metadata when customer visibility is allowed', async () => {
    authState.permissions = ['returns.view', 'customers.view']
    const { wrapper } = await mountView('/returns/11')

    expect(wrapper.text()).toContain('Customer:')
    expect(wrapper.text()).toContain('Mina Petrova')
    expect(wrapper.text()).toContain('mina@example.com')
  })

  it('skips customer lookups when the role cannot view customers', async () => {
    authState.permissions = ['returns.view']

    await mountView('/returns/11')

    expect(customerQueryCalls).toContain(0)
    expect(customerQueryCalls).not.toContain(12)
  })

  it('renders add-item controls without order permissions', async () => {
    authState.permissions = ['returns.view', 'returns.item.add']
    const { wrapper } = await mountView('/returns/11')

    expect(wrapper.find('[data-test="returns-add-item-form"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Winter Jacket (JKT-301)')
  })

  it('shows add-item validation and server field errors', async () => {
    authState.permissions = ['returns.view', 'returns.item.add']

    mutationState.addItem = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'Validation failed.',
          errors: {
            quantity: ['Too many items.'],
          },
        },
      },
    })

    const { wrapper } = await mountView('/returns/11')

    await wrapper.get('[data-test="returns-add-item-quantity"]').setValue('0')
    await wrapper.get('[data-test="returns-add-item-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Quantity must be a positive whole number.')

    await wrapper.get('[data-test="returns-add-item-quantity"]').setValue('1')
    await wrapper.get('[data-test="returns-add-item-form"]').trigger('submit')
    await flushPromises()

    expect(mutationState.addItem).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Too many items.')
  })

  it('shows a plus icon on the add item submit button', async () => {
    authState.permissions = ['returns.view', 'returns.item.add']
    const { wrapper } = await mountView('/returns/11')

    expect(wrapper.find('[data-test="returns-add-item-submit"] svg').exists()).toBe(true)
  })
})
