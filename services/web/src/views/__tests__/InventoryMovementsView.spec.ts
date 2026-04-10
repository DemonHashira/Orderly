import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import InventoryMovementsView from '@/views/InventoryMovementsView.vue'
import { useListUiStateStore } from '@/stores/list-ui-state'

const authState = vi.hoisted(() => ({
  permissions: ['inventory.view', 'inventory.movement.create', 'products.view'] as string[],
}))

const inventoryState = vi.hoisted(() => ({
  movements: [
    {
      id: 401,
      product_id: 301,
      type: 'adjustment',
      quantity_delta: -2,
      reason: 'Manual recount',
      created_at: '2026-03-09T08:00:00Z',
      product: {
        id: 301,
        name: 'Winter Jacket',
        sku: 'JKT-301',
      },
    },
  ],
}))

const productState = vi.hoisted(() => ({
  products: [
    {
      id: 301,
      name: 'Winter Jacket',
      sku: 'JKT-301',
      sale_price: '99.00',
      description: null,
      is_active: true,
      created_at: '2026-03-09T08:00:00Z',
      updated_at: '2026-03-09T08:00:00Z',
    },
  ],
  enabledCalls: [] as boolean[],
}))

const mutationState = vi.hoisted(() => ({
  create: vi.fn(),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/inventory/composables/useInventoryQueries', () => ({
  useInventoryMovementsQuery: () => ({
    data: computed(() => ({
      data: inventoryState.movements,
      meta: {
        current_page: 1,
        last_page: 2,
        total: inventoryState.movements.length,
        per_page: 15,
      },
    })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useCreateInventoryMovementMutation: () => ({
    mutateAsync: mutationState.create,
    isPending: ref(false),
  }),
}))

vi.mock('@/features/products/composables/useProductsQueries', () => ({
  useProductsQuery: (_params: unknown, options?: { enabled?: { value: boolean } }) => {
    productState.enabledCalls.push(options?.enabled?.value ?? true)

    return {
      data: computed(() => ({
        data: productState.products,
      })),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
}))

const DialogStub = {
  props: ['open'],
  emits: ['update:open'],
  template: '<div v-if="open"><slot /></div>',
}

const InventoryMovementsDataTableStub = {
  emits: ['update:page', 'update:per-page'],
  template: `
    <div>
      <button
        type="button"
        data-test="movements-per-page"
        @click="$emit('update:per-page', 25)"
      >
        Per page
      </button>
    </div>
  `,
}

const InventoryProductComboboxStub = {
  props: ['modelValue', 'searchValue', 'options', 'dataTest'],
  emits: ['update:modelValue', 'update:searchValue'],
  template: `
    <div>
      <input
        :data-test="dataTest"
        :value="searchValue"
        @input="$emit('update:searchValue', $event.target.value)"
      />
      <button
        v-if="options.length > 0"
        type="button"
        :data-test="dataTest ? dataTest + '-select-first' : undefined"
        @click="
          $emit('update:modelValue', String(options[0].id));
          $emit('update:searchValue', options[0].label)
        "
      >
        Select first
      </button>
    </div>
  `,
}

describe('InventoryMovementsView', () => {
  beforeEach(() => {
    authState.permissions = ['inventory.view', 'inventory.movement.create', 'products.view']
    productState.enabledCalls = []
    mutationState.create = vi.fn().mockResolvedValue({
      data: {
        movement: inventoryState.movements[0],
      },
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const mountView = async (path = '/inventory/movements') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/inventory/movements',
          name: 'inventory-movements',
          component: InventoryMovementsView,
        },
      ],
    })
    await router.replace(path)

    const pinia = createPinia()

    const wrapper = mount(InventoryMovementsView, {
      global: {
        plugins: [pinia, router],
        stubs: {
          InventoryMovementsDataTable: InventoryMovementsDataTableStub,
          InventoryProductCombobox: InventoryProductComboboxStub,
          DateRangeFilter: { template: '<div />' },
          PageHeader: { template: '<div><slot name="actions" /></div>' },
          PageInitialSkeleton: { template: '<div />' },
          PageRefetchOverlay: { template: '<div />' },
          ApiErrorAlert: { template: '<div><slot /></div>' },
          EmptyStateCard: { template: '<div />' },
          Dialog: DialogStub,
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          DialogDescription: { template: '<div><slot /></div>' },
          Alert: { template: '<div><slot /></div>' },
          AlertTitle: { template: '<div><slot /></div>' },
          AlertDescription: { template: '<div><slot /></div>' },
          Field: { template: '<div><slot /></div>' },
          FieldGroup: { template: '<div><slot /></div>' },
          FieldLabel: { template: '<label><slot /></label>' },
          FieldError: { props: ['errors'], template: '<div>{{ errors?.[0] }}</div>' },
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

  it('syncs movement search input to route query', async () => {
    vi.useFakeTimers()
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="inventory-movements-search"]').setValue('jacket')
    await vi.advanceTimersByTimeAsync(320)
    await flushPromises()

    expect(router.currentRoute.value.query.q).toBe('jacket')
  })

  it('hydrates movement filters from route query', async () => {
    const { pinia } = await mountView(
      '/inventory/movements?q=sku&type=damage&product_id=301&created_from=2026-03-01&created_to=2026-03-10&page=2',
    )
    const listUiStore = useListUiStateStore(pinia)

    expect(listUiStore.modules.inventory_movements.q).toBe('sku')
    expect(listUiStore.modules.inventory_movements.type).toBe('damage')
    expect(listUiStore.modules.inventory_movements.product_id).toBe('301')
    expect(listUiStore.modules.inventory_movements.created_from).toBe('2026-03-01')
    expect(listUiStore.modules.inventory_movements.created_to).toBe('2026-03-10')
    expect(listUiStore.modules.inventory_movements.page).toBe(2)
  })

  it('preloads active products for the filter combobox so the first open is not empty', async () => {
    await mountView()

    expect(productState.enabledCalls[0]).toBe(true)
  })

  it('resets movement filters back to defaults', async () => {
    const { wrapper, router, pinia } = await mountView(
      '/inventory/movements?q=sku&type=damage&product_id=301&created_from=2026-03-01&created_to=2026-03-10&page=2',
    )
    const listUiStore = useListUiStateStore(pinia)

    const resetButton = wrapper.findAll('button').find((button) => button.text() === 'Reset')
    expect(resetButton).toBeTruthy()
    await resetButton!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({})
    expect(listUiStore.modules.inventory_movements.q).toBe('')
    expect(listUiStore.modules.inventory_movements.type).toBe('')
    expect(listUiStore.modules.inventory_movements.product_id).toBe('')
    expect(listUiStore.modules.inventory_movements.created_from).toBe('')
    expect(listUiStore.modules.inventory_movements.created_to).toBe('')
    expect(listUiStore.modules.inventory_movements.page).toBe(1)
  })

  it('hides manual movement creation without permission', async () => {
    authState.permissions = ['inventory.view', 'products.view']
    const { wrapper } = await mountView()

    expect(wrapper.find('[data-test="inventory-open-create-dialog"]').exists()).toBe(false)
  })

  it('shows client validation and server field errors for movement creation', async () => {
    mutationState.create = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'Validation failed.',
          errors: {
            reason: ['Too vague.'],
          },
        },
      },
    })

    const { wrapper } = await mountView()

    await wrapper.get('[data-test="inventory-open-create-dialog"]').trigger('click')
    await flushPromises()

    await wrapper.get('[data-test="inventory-create-product-select-first"]').trigger('click')
    await wrapper.get('[data-test="inventory-create-reason"]').setValue('Inventory recount')
    await wrapper.get('[data-test="inventory-create-quantity"]').setValue('0')
    await wrapper.get('[data-test="inventory-create-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Quantity delta must be a non-zero whole number.')

    await wrapper.get('[data-test="inventory-create-quantity"]').setValue('2')
    await wrapper.get('[data-test="inventory-create-form"]').trigger('submit')
    await flushPromises()

    expect(mutationState.create).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Too vague.')
  })

  it('syncs per-page changes to route query', async () => {
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="movements-per-page"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.per_page).toBe('25')
  })
})
