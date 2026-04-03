import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import ProductsView from '@/views/ProductsView.vue'
import { useListUiStateStore } from '@/stores/list-ui-state'

const authState = vi.hoisted(() => ({
  permissions: [
    'products.view',
    'products.manage',
    'products.import',
    'products.export',
    'inventory.view',
  ] as string[],
}))

const productState = vi.hoisted(() => ({
  products: [
    {
      id: 301,
      sku: 'JKT-301',
      name: 'Winter Jacket',
      sale_price: '99.00',
      description: 'Quilted winter jacket',
      is_active: true,
      created_at: '2026-03-09T08:00:00Z',
      updated_at: '2026-03-10T10:00:00Z',
    },
    {
      id: 302,
      sku: 'HD-302',
      name: 'Archive Hoodie',
      sale_price: '79.00',
      description: 'Archived hoodie',
      is_active: false,
      created_at: '2026-03-08T08:00:00Z',
      updated_at: '2026-03-09T10:00:00Z',
    },
  ],
  detail: {
    id: 301,
    sku: 'JKT-301',
    name: 'Winter Jacket',
    sale_price: '99.00',
    description: 'Quilted winter jacket',
    is_active: true,
    created_at: '2026-03-09T08:00:00Z',
    updated_at: '2026-03-10T10:00:00Z',
  },
  stock: {
    product: {
      id: 301,
      sku: 'JKT-301',
      name: 'Winter Jacket',
      is_active: true,
    },
    qty_on_hand: 10,
    qty_reserved: 3,
    available: 7,
  },
}))

const mutationState = vi.hoisted(() => ({
  create: vi.fn(),
  update: vi.fn(),
  archive: vi.fn(),
  importProducts: vi.fn(),
  exportProducts: vi.fn(),
}))

const productsQueryEnabledStates = vi.hoisted(() => [] as boolean[])

const toastState = vi.hoisted(() => ({
  success: vi.fn(),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/products/composables/useProductsQueries', () => ({
  useProductsQuery: (_params: unknown, options?: { enabled?: { value?: boolean } | boolean }) => {
    productsQueryEnabledStates.push(
      options?.enabled === undefined
        ? true
        : typeof options.enabled === 'boolean'
          ? options.enabled
          : Boolean(options.enabled.value),
    )

    return {
      data: computed(() => ({
        data: productState.products,
        meta: {
          current_page: 1,
          last_page: 3,
          total: productState.products.length,
          per_page: 15,
        },
      })),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
  useProductQuery: () => ({
    data: computed(() => ({
      data: productState.detail,
    })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useCreateProductMutation: () => ({
    mutateAsync: mutationState.create,
    isPending: ref(false),
  }),
  useUpdateProductMutation: () => ({
    mutateAsync: mutationState.update,
    isPending: ref(false),
  }),
  useArchiveProductMutation: () => ({
    mutateAsync: mutationState.archive,
    isPending: ref(false),
  }),
  useImportProductsMutation: () => ({
    mutateAsync: mutationState.importProducts,
    isPending: ref(false),
  }),
  useExportProductsMutation: () => ({
    mutateAsync: mutationState.exportProducts,
    isPending: ref(false),
  }),
}))

vi.mock('@/features/inventory/composables/useInventoryQueries', () => ({
  useInventoryStocksQuery: () => ({
    data: computed(() => ({
      data: [productState.stock],
    })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
}))

vi.mock('vue-sonner', () => ({
  toast: {
    success: toastState.success,
  },
}))

const DialogStub = {
  props: ['open'],
  emits: ['update:open'],
  template: '<div v-if="open"><slot /></div>',
}

const AlertDialogStub = {
  props: ['open'],
  emits: ['update:open'],
  template: '<div v-if="open"><slot /></div>',
}

const ProductsDataTableStub = {
  emits: ['view', 'edit', 'archive', 'update:page', 'update:per-page'],
  template: `
    <div>
      <button type="button" data-test="products-table-view" @click="$emit('view', 301)">View</button>
      <button type="button" data-test="products-table-edit" @click="$emit('edit', 301)">Edit</button>
      <button type="button" data-test="products-table-archive" @click="$emit('archive', 301)">Archive</button>
      <button type="button" data-test="products-table-per-page" @click="$emit('update:per-page', 25)">Per page</button>
    </div>
  `,
}

describe('ProductsView', () => {
  beforeEach(() => {
    productsQueryEnabledStates.length = 0
    authState.permissions = [
      'products.view',
      'products.manage',
      'products.import',
      'products.export',
      'inventory.view',
    ]
    mutationState.create = vi.fn().mockResolvedValue({ data: { ...productState.detail, id: 401 } })
    mutationState.update = vi.fn().mockResolvedValue({ data: productState.detail })
    mutationState.archive = vi
      .fn()
      .mockResolvedValue({ data: { ...productState.detail, is_active: false } })
    mutationState.importProducts = vi.fn().mockResolvedValue({
      total_rows: 2,
      created: 1,
      updated: 1,
      failed: 0,
      errors: [],
    })
    mutationState.exportProducts = vi.fn().mockResolvedValue({
      blob: new Blob(['sku,name']),
      filename: 'products.csv',
    })
    toastState.success.mockReset()
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock')
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  const mountView = async (path = '/products') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/products',
          name: 'products',
          component: ProductsView,
        },
        {
          path: '/products/new',
          name: 'product-create',
          component: ProductsView,
        },
        {
          path: '/products/:id',
          name: 'product-detail',
          component: ProductsView,
        },
        {
          path: '/products/:id/edit',
          name: 'product-edit',
          component: ProductsView,
        },
      ],
    })
    await router.replace(path)

    const pinia = createPinia()
    const appendSpy = vi.spyOn(document.body, 'appendChild')
    const removeSpy = vi.spyOn(document.body, 'removeChild')

    const wrapper = mount(ProductsView, {
      attachTo: document.body,
      global: {
        plugins: [pinia, router],
        stubs: {
          ProductsDataTable: ProductsDataTableStub,
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
          AlertDialog: AlertDialogStub,
          AlertDialogContent: { template: '<div><slot /></div>' },
          AlertDialogHeader: { template: '<div><slot /></div>' },
          AlertDialogTitle: { template: '<div><slot /></div>' },
          AlertDialogDescription: { template: '<div><slot /></div>' },
          AlertDialogFooter: { template: '<div><slot /></div>' },
          AlertDialogCancel: { template: '<button type="button"><slot /></button>' },
          Alert: { template: '<div><slot /></div>' },
          AlertTitle: { template: '<div><slot /></div>' },
          AlertDescription: { template: '<div><slot /></div>' },
          Card: { template: '<div><slot /></div>' },
          CardHeader: { template: '<div><slot /></div>' },
          CardTitle: { template: '<div><slot /></div>' },
          CardDescription: { template: '<div><slot /></div>' },
          CardContent: { template: '<div><slot /></div>' },
          Field: { template: '<div><slot /></div>' },
          FieldGroup: { template: '<div><slot /></div>' },
          FieldLabel: { template: '<label><slot /></label>' },
          FieldError: { props: ['errors'], template: '<div>{{ errors?.[0] }}</div>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuGroup: { template: '<div><slot /></div>' },
          DropdownMenuItem: {
            template: '<button type="button" @click="$emit(\'select\')"><slot /></button>',
          },
          Select: { template: '<div><slot /></div>' },
          SelectTrigger: { template: '<div><slot /></div>' },
          SelectValue: { template: '<div><slot /></div>' },
          SelectContent: { template: '<div><slot /></div>' },
          SelectGroup: { template: '<div><slot /></div>' },
          SelectItem: { template: '<div><slot /></div>' },
          StatusBadge: { template: '<div><slot /></div>' },
        },
      },
    })

    await flushPromises()
    return { wrapper, router, pinia, appendSpy, removeSpy }
  }

  it('syncs search input to route query', async () => {
    vi.useFakeTimers()
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="products-search"]').setValue('jacket')
    await vi.advanceTimersByTimeAsync(320)
    await flushPromises()

    expect(router.currentRoute.value.query.q).toBe('jacket')
  })

  it('hydrates filters from route query', async () => {
    const { pinia } = await mountView('/products?q=hoodie&status=archived&page=2&per_page=25')
    const listUiStore = useListUiStateStore(pinia)

    expect(listUiStore.modules.products.q).toBe('hoodie')
    expect(listUiStore.modules.products.status).toBe('archived')
    expect(listUiStore.modules.products.page).toBe(2)
    expect(listUiStore.modules.products.per_page).toBe(25)
  })

  it('resets product filters back to defaults', async () => {
    const { wrapper, router, pinia } = await mountView('/products?q=hoodie&status=archived&page=2')
    const listUiStore = useListUiStateStore(pinia)

    const resetButton = wrapper.findAll('button').find((button) => button.text() === 'Reset')
    expect(resetButton).toBeTruthy()
    await resetButton!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({})
    expect(listUiStore.modules.products.q).toBe('')
    expect(listUiStore.modules.products.status).toBe('')
    expect(listUiStore.modules.products.page).toBe(1)
    expect(listUiStore.modules.products.per_page).toBe(15)
  })

  it('opens route-driven dialogs from table actions', async () => {
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="products-table-view"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('product-detail')

    await wrapper.get('[data-test="products-table-edit"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('product-edit')
  })

  it('hides manage, import, and export controls without permissions', async () => {
    authState.permissions = ['products.view']
    const { wrapper } = await mountView()

    expect(wrapper.find('[data-test="products-open-create"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="products-open-import"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="products-open-export"]').exists()).toBe(false)
  })

  it('shows a plus icon on the create product submit button', async () => {
    const { wrapper } = await mountView('/products/new')

    expect(wrapper.find('[data-test="products-form-submit"] svg').exists()).toBe(true)
  })

  it('shows duplicate sku validation errors from product form', async () => {
    mutationState.create = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'Validation failed.',
          errors: {
            sku: ['The sku has already been taken.'],
          },
        },
      },
    })

    const { wrapper } = await mountView('/products/new')

    await wrapper.get('[data-test="products-form-sku"]').setValue('dup-001')
    await wrapper.get('[data-test="products-form-name"]').setValue('Duplicate SKU')
    await wrapper.get('[data-test="products-form-sale-price"]').setValue('12.00')
    await wrapper.get('[data-test="products-form"]').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('The sku has already been taken.')
  })

  it('blocks submit when client-side product validation fails', async () => {
    const { wrapper } = await mountView('/products/new')

    await wrapper.get('[data-test="products-form"]').trigger('submit.prevent')
    await flushPromises()

    expect(mutationState.create).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('SKU is required.')
    expect(wrapper.text()).toContain('Name is required.')
    expect(wrapper.text()).toContain('Sale price is required.')
  })

  it('revalidates touched product fields on input', async () => {
    const { wrapper } = await mountView('/products/new')

    await wrapper.get('[data-test="products-form-name"]').trigger('blur')
    await flushPromises()
    expect(wrapper.text()).toContain('Name is required.')

    await wrapper.get('[data-test="products-form-name"]').setValue('Winter Jacket')
    await flushPromises()

    expect(wrapper.text()).not.toContain('Name is required.')
  })

  it('clears server sku validation errors when the sku changes', async () => {
    mutationState.create = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'Validation failed.',
          errors: {
            sku: ['The sku has already been taken.'],
          },
        },
      },
    })

    const { wrapper } = await mountView('/products/new')

    await wrapper.get('[data-test="products-form-sku"]').setValue('dup-001')
    await wrapper.get('[data-test="products-form-name"]').setValue('Duplicate SKU')
    await wrapper.get('[data-test="products-form-sale-price"]').setValue('12.00')
    await wrapper.get('[data-test="products-form"]').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('The sku has already been taken.')

    await wrapper.get('[data-test="products-form-sku"]').setValue('unique-001')
    await flushPromises()

    expect(wrapper.text()).not.toContain('The sku has already been taken.')
  })

  it('fills the sku field from the name helper', async () => {
    const { wrapper } = await mountView('/products/new')

    await wrapper.get('[data-test="products-form-name"]').setValue('Winter Jacket')
    await wrapper.get('[data-test="products-form-generate-sku"]').trigger('click')
    await flushPromises()

    expect((wrapper.get('[data-test="products-form-sku"]').element as HTMLInputElement).value).toBe(
      'WINTER-JACKET',
    )
  })

  it('submits a normalized create payload', async () => {
    const { wrapper } = await mountView('/products/new')

    await wrapper.get('[data-test="products-form-sku"]').setValue('  abc-123  ')
    await wrapper.get('[data-test="products-form-name"]').setValue('  Winter Jacket  ')
    await wrapper.get('[data-test="products-form-sale-price"]').setValue('12.50')
    await wrapper.get('[data-test="products-form-description"]').setValue('  Quilted shell  ')
    await wrapper.get('[data-test="products-form"]').trigger('submit.prevent')
    await flushPromises()

    expect(mutationState.create).toHaveBeenCalledWith({
      sku: 'ABC-123',
      name: 'Winter Jacket',
      sale_price: '12.5',
      description: 'Quilted shell',
      is_active: true,
    })
  })

  it('uses the same validation flow on edit routes', async () => {
    const { wrapper } = await mountView('/products/301/edit')

    await wrapper.get('[data-test="products-form-name"]').setValue('')
    await wrapper.get('[data-test="products-form-name"]').trigger('blur')
    await flushPromises()

    expect(wrapper.text()).toContain('Name is required.')

    await wrapper.get('[data-test="products-form"]').trigger('submit.prevent')
    await flushPromises()

    expect(mutationState.update).not.toHaveBeenCalled()
  })

  it('archives products from the confirmation dialog', async () => {
    const { wrapper } = await mountView()

    await wrapper.get('[data-test="products-table-archive"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-test="products-archive-confirm"]').trigger('click')
    await flushPromises()

    expect(mutationState.archive).toHaveBeenCalledWith(301)
    expect(toastState.success).toHaveBeenCalledWith('Product archived successfully.')
  })

  it('exports products using current filters', async () => {
    vi.useFakeTimers()
    const clickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => undefined)
    const { wrapper } = await mountView()

    await wrapper.get('[data-test="products-search"]').setValue('jacket')
    await vi.advanceTimersByTimeAsync(320)
    await flushPromises()
    await wrapper.vm.$nextTick()

    await wrapper.get('[data-test="products-export-csv"]').trigger('click')
    await flushPromises()

    expect(mutationState.exportProducts).toHaveBeenCalledWith({
      format: 'csv',
      q: 'jacket',
      is_active: undefined,
    })
    expect(clickSpy).toHaveBeenCalled()
    expect(toastState.success).toHaveBeenCalledWith('Product export started (CSV).', {
      description: 'Your download should begin automatically.',
    })
  })

  it('renders import summary after a successful import', async () => {
    const { wrapper } = await mountView()

    await wrapper.get('[data-test="products-open-import"]').trigger('click')
    const file = new File(['sku,name,sale_price'], 'products.csv', { type: 'text/csv' })
    const input = wrapper.get('[data-test="products-import-file"]')
    Object.defineProperty(input.element, 'files', {
      value: [file],
      configurable: true,
    })
    await input.trigger('change')
    await wrapper.get('[data-test="products-import-form"]').trigger('submit.prevent')
    await flushPromises()

    expect(mutationState.importProducts).toHaveBeenCalledWith(file)
    expect(wrapper.text()).toContain('Created 1, updated 1, failed 0 out of 2 rows.')
    expect(toastState.success).toHaveBeenCalledWith('Product import completed.', {
      description: 'Created 1, updated 1, failed 0.',
    })
  })

  it('shows a blocked import summary when row errors are returned', async () => {
    mutationState.importProducts = vi.fn().mockResolvedValue({
      total_rows: 2,
      created: 0,
      updated: 0,
      failed: 1,
      errors: [{ row: 3, message: 'Duplicate SKU in file: ABC-1' }],
    })

    const { wrapper } = await mountView()

    await wrapper.get('[data-test="products-open-import"]').trigger('click')
    const file = new File(['sku,name,sale_price'], 'products.csv', { type: 'text/csv' })
    const input = wrapper.get('[data-test="products-import-file"]')
    Object.defineProperty(input.element, 'files', {
      value: [file],
      configurable: true,
    })
    await input.trigger('change')
    await wrapper.get('[data-test="products-import-form"]').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('No changes were applied. 1 out of 2 rows failed validation.')
    expect(wrapper.text()).toContain('Row 3: Duplicate SKU in file: ABC-1')
    expect(toastState.success).toHaveBeenCalledWith('Product import blocked.', {
      description: 'No changes were applied because 1 row failed validation.',
    })
  })

  it('syncs rows-per-page changes to the route query', async () => {
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="products-table-per-page"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.per_page).toBe('25')
  })

  it('skips the product list query on create routes for manage-only users', async () => {
    authState.permissions = ['products.manage']

    await mountView('/products/new')

    expect(productsQueryEnabledStates[productsQueryEnabledStates.length - 1]).toBe(false)
  })
})
