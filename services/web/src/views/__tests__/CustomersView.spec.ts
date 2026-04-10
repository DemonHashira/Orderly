import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import CustomersView from '@/views/CustomersView.vue'
import { useListUiStateStore } from '@/stores/list-ui-state'
import type { Customer, Order } from '@/types'

const authState = vi.hoisted(() => ({
  permissions: [
    'customers.view',
    'customers.create',
    'customers.update',
    'customers.delete',
    'orders.view',
  ] as string[],
}))

const customersState = vi.hoisted(() => ({
  list: [
    {
      id: 101,
      name: 'Mina Petrova',
      first_name: 'Mina',
      middle_name: null,
      last_name: 'Petrova',
      email: 'mina@example.com',
      phone: '+359888000111',
      address: {
        country: 'Bulgaria',
        city: 'Sofia',
        postal_code: '1000',
        address_line1: 'Tsar Osvoboditel 1',
        address_line2: 'Floor 2',
      },
    },
    {
      id: 102,
      name: 'Ivan Georgiev',
      first_name: 'Ivan',
      middle_name: null,
      last_name: 'Georgiev',
      email: 'ivan@example.com',
      phone: '+359888000222',
      address: null,
    },
  ] as Customer[],
  detail: {
    id: 101,
    name: 'Mina Petrova',
    first_name: 'Mina',
    middle_name: null,
    last_name: 'Petrova',
    email: 'mina@example.com',
    phone: '+359888000111',
    address: {
      country: 'Bulgaria',
      city: 'Sofia',
      postal_code: '1000',
      address_line1: 'Tsar Osvoboditel 1',
      address_line2: 'Floor 2',
    },
  } as Customer,
}))

const ordersState = vi.hoisted(() => ({
  list: [
    {
      id: 201,
      reference: 'ORD-201',
      customer_id: 101,
      sales_channel_id: 3,
      created_by: 1,
      current_status: 'delivered',
      total_amount: '129.00',
      internal_notes: null,
      created_at: '2026-03-08T10:00:00Z',
      updated_at: '2026-03-09T10:00:00Z',
      items: [],
      status_history: [],
    },
  ] as Order[],
}))

const mutationState = vi.hoisted(() => ({
  create: vi.fn(),
  update: vi.fn(),
  deleteCustomer: vi.fn(),
}))

const customersQueryEnabledStates = vi.hoisted(() => [] as boolean[])

const toastState = vi.hoisted(() => ({
  success: vi.fn(),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/customers/composables/useCustomersQueries', () => ({
  useCustomersQuery: (_params: unknown, options?: { enabled?: { value?: boolean } | boolean }) => {
    customersQueryEnabledStates.push(
      options?.enabled === undefined
        ? true
        : typeof options.enabled === 'boolean'
          ? options.enabled
          : Boolean(options.enabled.value),
    )

    return {
      data: computed(() => ({
        data: customersState.list,
        meta: {
          current_page: 1,
          last_page: 3,
          total: customersState.list.length,
          per_page: 15,
        },
      })),
      isLoading: ref(false),
      isFetching: ref(false),
      error: ref(null),
    }
  },
  useCustomerQuery: () => ({
    data: computed(() => ({
      data: customersState.detail,
    })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useCreateCustomerMutation: () => ({
    mutateAsync: mutationState.create,
    isPending: ref(false),
  }),
  useUpdateCustomerMutation: () => ({
    mutateAsync: mutationState.update,
    isPending: ref(false),
  }),
  useDeleteCustomerMutation: () => ({
    mutateAsync: mutationState.deleteCustomer,
    isPending: ref(false),
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
        per_page: 5,
      },
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

const CustomersDataTableStub = {
  props: ['canManageCustomers'],
  emits: ['view', 'edit', 'delete', 'update:page', 'update:per-page'],
  template: `
    <div>
      <button type="button" data-test="customers-table-view" @click="$emit('view', 101)">View</button>
      <button
        v-if="canManageCustomers"
        type="button"
        data-test="customers-table-edit"
        @click="$emit('edit', 101)"
      >
        Edit
      </button>
      <button
        v-if="canManageCustomers"
        type="button"
        data-test="customers-table-delete"
        @click="$emit('delete', 101)"
      >
        Delete
      </button>
      <button
        type="button"
        data-test="customers-table-per-page"
        @click="$emit('update:per-page', 25)"
      >
        Per page
      </button>
    </div>
  `,
}

describe('CustomersView', () => {
  beforeEach(() => {
    customersQueryEnabledStates.length = 0
    authState.permissions = [
      'customers.view',
      'customers.create',
      'customers.update',
      'customers.delete',
      'orders.view',
    ]
    customersState.detail = { ...customersState.list[0]! }
    ordersState.list = [
      {
        id: 201,
        reference: 'ORD-201',
        customer_id: 101,
        sales_channel_id: 3,
        created_by: 1,
        current_status: 'delivered',
        total_amount: '129.00',
        internal_notes: null,
        created_at: '2026-03-08T10:00:00Z',
        updated_at: '2026-03-09T10:00:00Z',
        items: [],
        status_history: [],
      },
    ]
    mutationState.create = vi.fn().mockResolvedValue({
      data: {
        id: 201,
        name: 'Nina Koleva',
        first_name: 'Nina',
        middle_name: null,
        last_name: 'Koleva',
        email: 'nina@example.com',
        phone: '+359888000333',
        address: {
          country: 'Bulgaria',
          city: 'Sofia',
          postal_code: '1000',
          address_line1: 'Tsar Osvoboditel 1',
          address_line2: 'Floor 2',
        },
      },
    })
    mutationState.update = vi.fn().mockResolvedValue({
      data: {
        ...customersState.detail,
        first_name: 'Mina Updated',
        name: 'Mina Updated Petrova',
      },
    })
    mutationState.deleteCustomer = vi.fn().mockResolvedValue(undefined)
    toastState.success.mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const mountView = async (path = '/customers') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/customers', name: 'customers', component: CustomersView },
        { path: '/customers/new', name: 'customer-create', component: CustomersView },
        { path: '/customers/:id', name: 'customer-detail', component: CustomersView },
        { path: '/customers/:id/edit', name: 'customer-edit', component: CustomersView },
        { path: '/orders/:id', name: 'order-detail', component: { template: '<div />' } },
      ],
    })
    await router.replace(path)

    const pinia = createPinia()
    const wrapper = mount(CustomersView, {
      global: {
        plugins: [pinia, router],
        stubs: {
          CustomersDataTable: CustomersDataTableStub,
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
          Card: { template: '<div><slot /></div>' },
          CardHeader: { template: '<div><slot /></div>' },
          CardTitle: { template: '<div><slot /></div>' },
          CardDescription: { template: '<div><slot /></div>' },
          CardContent: { template: '<div><slot /></div>' },
          Field: { template: '<div><slot /></div>' },
          FieldGroup: { template: '<div><slot /></div>' },
          FieldLabel: { template: '<label><slot /></label>' },
          FieldError: { props: ['errors'], template: '<div>{{ errors?.[0] }}</div>' },
          StatusBadge: { template: '<span><slot /></span>' },
        },
      },
    })

    await flushPromises()
    return { wrapper, router, pinia }
  }

  it('syncs search input to route query', async () => {
    vi.useFakeTimers()
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="customers-search"]').setValue('mina')
    await vi.advanceTimersByTimeAsync(320)
    await flushPromises()

    expect(router.currentRoute.value.query.q).toBe('mina')
  })

  it('hydrates customer list state from route query', async () => {
    const { pinia } = await mountView('/customers?q=mina&page=2&per_page=25')
    const listUiStore = useListUiStateStore(pinia)

    expect(listUiStore.modules.customers.q).toBe('mina')
    expect(listUiStore.modules.customers.page).toBe(2)
    expect(listUiStore.modules.customers.per_page).toBe(25)
  })

  it('opens route-driven detail and edit dialogs from table actions', async () => {
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="customers-table-view"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('customer-detail')

    await wrapper.get('[data-test="customers-table-edit"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('customer-edit')
  })

  it('shows create and management controls only with permissions', async () => {
    authState.permissions = ['customers.view']
    const withoutPermission = await mountView()

    expect(withoutPermission.wrapper.find('[data-test="customers-open-create"]').exists()).toBe(
      false,
    )
    expect(withoutPermission.wrapper.find('[data-test="customers-table-edit"]').exists()).toBe(
      false,
    )
    expect(withoutPermission.wrapper.find('[data-test="customers-table-delete"]').exists()).toBe(
      false,
    )

    authState.permissions = [
      'customers.view',
      'customers.create',
      'customers.update',
      'customers.delete',
    ]
    const withPermission = await mountView()

    expect(withPermission.wrapper.find('[data-test="customers-open-create"]').exists()).toBe(true)
    expect(withPermission.wrapper.find('[data-test="customers-table-edit"]').exists()).toBe(true)
    expect(withPermission.wrapper.find('[data-test="customers-table-delete"]').exists()).toBe(true)
  })

  it('shows local validation and server field errors when creating a customer', async () => {
    mutationState.create = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'Validation failed.',
          errors: {
            email: ['This email is already used by another customer in your organization.'],
          },
        },
      },
    })

    const { wrapper } = await mountView('/customers/new')

    await wrapper.get('[data-test="customers-form-submit"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('First name is required.')

    await wrapper.get('[data-test="customers-form-first-name"]').setValue('Mina')
    await wrapper.get('[data-test="customers-form-last-name"]').setValue('Petrova')
    await wrapper.get('[data-test="customers-form-phone"]').setValue('+359888000111')
    await wrapper.get('[data-test="customers-form-email"]').setValue('mina@example.com')
    await wrapper.get('[data-test="customers-form-submit"]').trigger('click')
    await flushPromises()

    expect(mutationState.create).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('City is required.')
    expect(wrapper.text()).toContain('Postal code is required.')
    expect(wrapper.text()).toContain('Address line 1 is required.')
  })

  it('shows server field errors when creating a customer', async () => {
    mutationState.create = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'Validation failed.',
          errors: {
            email: ['This email is already used by another customer in your organization.'],
          },
        },
      },
    })

    const { wrapper } = await mountView('/customers/new')

    await wrapper.get('[data-test="customers-form-first-name"]').setValue('Mina')
    await wrapper.get('[data-test="customers-form-last-name"]').setValue('Petrova')
    await wrapper.get('[data-test="customers-form-phone"]').setValue('+359888000111')
    await wrapper.get('[data-test="customers-form-email"]').setValue('mina@example.com')
    await wrapper.get('[data-test="customers-form-address-city"]').setValue('Sofia')
    await wrapper.get('[data-test="customers-form-address-postal-code"]').setValue('1000')
    await wrapper.get('[data-test="customers-form-address-line1"]').setValue('Tsar Osvoboditel 1')
    await wrapper.get('[data-test="customers-form-submit"]').trigger('click')
    await flushPromises()

    expect(mutationState.create).toHaveBeenCalled()
    expect(wrapper.text()).toContain(
      'This email is already used by another customer in your organization.',
    )
  })

  it('shows customer field validation on blur before submit', async () => {
    const { wrapper } = await mountView('/customers/new')

    await wrapper.get('[data-test="customers-form-first-name"]').trigger('blur')
    await flushPromises()

    expect(wrapper.text()).toContain('First name is required.')
    expect(mutationState.create).not.toHaveBeenCalled()

    await wrapper.get('[data-test="customers-form-first-name"]').setValue('Mina')
    await wrapper.get('[data-test="customers-form-first-name"]').trigger('blur')
    await flushPromises()

    expect(wrapper.text()).not.toContain('First name is required.')
  })

  it('prefills the address country and validates only the touched address field on blur', async () => {
    const { wrapper } = await mountView('/customers/new')

    const countryInput = wrapper.get('[data-test="customers-form-address-country"]')
    expect((countryInput.element as HTMLInputElement).value).toBe('Bulgaria')

    await wrapper.get('[data-test="customers-form-address-postal-code"]').trigger('blur')
    await flushPromises()

    expect(wrapper.text()).toContain('Postal code is required.')
    expect(wrapper.text()).not.toContain('City is required.')
    expect(wrapper.text()).not.toContain('Address line 1 is required.')
  })

  it('keeps address validation scoped to each touched field while editing', async () => {
    const { wrapper } = await mountView('/customers/new')

    await wrapper.get('[data-test="customers-form-address-postal-code"]').trigger('blur')
    await flushPromises()

    expect(wrapper.text()).toContain('Postal code is required.')
    expect(wrapper.text()).not.toContain('City is required.')

    await wrapper.get('[data-test="customers-form-address-postal-code"]').setValue('1000')
    await wrapper.get('[data-test="customers-form-address-postal-code"]').trigger('blur')
    await flushPromises()

    expect(wrapper.text()).not.toContain('Postal code is required.')
  })

  it('submits a nested address payload when the customer address is complete', async () => {
    const { wrapper } = await mountView('/customers/new')

    await wrapper.get('[data-test="customers-form-first-name"]').setValue('Mina')
    await wrapper.get('[data-test="customers-form-last-name"]').setValue('Petrova')
    await wrapper.get('[data-test="customers-form-phone"]').setValue('+359888000111')
    await wrapper.get('[data-test="customers-form-email"]').setValue('mina@example.com')
    await wrapper.get('[data-test="customers-form-address-city"]').setValue('Sofia')
    await wrapper.get('[data-test="customers-form-address-postal-code"]').setValue('1000')
    await wrapper.get('[data-test="customers-form-address-line1"]').setValue('Tsar Osvoboditel 1')
    await wrapper.get('[data-test="customers-form-address-line2"]').setValue('Floor 2')
    await wrapper.get('[data-test="customers-form-submit"]').trigger('click')
    await flushPromises()

    expect(mutationState.create).toHaveBeenCalledWith({
      first_name: 'Mina',
      middle_name: null,
      last_name: 'Petrova',
      phone: '+359888000111',
      email: 'mina@example.com',
      address: {
        country: 'Bulgaria',
        city: 'Sofia',
        postal_code: '1000',
        address_line1: 'Tsar Osvoboditel 1',
        address_line2: 'Floor 2',
      },
    })
  })

  it('shows server field errors when editing a customer', async () => {
    mutationState.update = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'Validation failed.',
          errors: {
            email: ['This email is already used by another customer in your organization.'],
          },
        },
      },
    })

    const { wrapper } = await mountView('/customers/101/edit')

    await wrapper.get('[data-test="customers-form-email"]').setValue('used@example.com')
    await wrapper.get('[data-test="customers-form-submit"]').trigger('click')
    await flushPromises()

    expect(mutationState.update).toHaveBeenCalled()
    expect(wrapper.text()).toContain(
      'This email is already used by another customer in your organization.',
    )
  })

  it('deletes a customer after confirmation', async () => {
    const { wrapper } = await mountView()

    await wrapper.get('[data-test="customers-table-delete"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-test="customers-delete-confirm"]').trigger('click')
    await flushPromises()

    expect(mutationState.deleteCustomer).toHaveBeenCalledWith(101)
    expect(toastState.success).toHaveBeenCalledWith('Customer deleted successfully.')
  })

  it('renders customer detail dialog with read-only order history from deep link', async () => {
    const { wrapper } = await mountView('/customers/101')

    expect(wrapper.find('[data-test="customers-detail-dialog"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Mina Petrova')
    expect(wrapper.text()).toContain('ORD-201')
    const addressLines = wrapper
      .findAll('[data-test="customers-detail-address-line"]')
      .map((node) => node.text())

    expect(addressLines).toEqual(['Tsar Osvoboditel 1', 'Floor 2', 'Sofia 1000', 'Bulgaria'])
  })

  it('syncs rows-per-page changes to the route query', async () => {
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-test="customers-table-per-page"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.per_page).toBe('25')
  })

  it('skips the list query on create routes for create-only users', async () => {
    authState.permissions = ['customers.create']

    await mountView('/customers/new')

    expect(customersQueryEnabledStates[customersQueryEnabledStates.length - 1]).toBe(false)
  })
})
