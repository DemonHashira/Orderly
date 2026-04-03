<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Plus, Search } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import OverlayScrollViewport from '@/components/ui/overlay-scroll/OverlayScrollViewport.vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useAuth } from '@/features/auth/composables/useAuth'
import {
  useCreateCustomerMutation,
  useCustomerQuery,
  useCustomersQuery,
  useDeleteCustomerMutation,
  useUpdateCustomerMutation,
} from '@/features/customers/composables/useCustomersQueries'
import {
  CUSTOMER_FORM_FIELDS_TO_VALIDATE,
  DEFAULT_CUSTOMER_ADDRESS_COUNTRY,
  createEmptyCustomerDialogForm,
  type CustomerDialogForm,
  type CustomerFormField,
  type CustomerUpsertPayload,
} from '@/features/customers/types'
import CustomerForm from '@/features/customers/ui/CustomerForm.vue'
import CustomersDataTable from '@/features/customers/ui/CustomersDataTable.vue'
import {
  validateCustomerDialogField,
  validateCustomerDialogForm,
} from '@/features/customers/validation/customer.schema'
import { useOrdersQuery } from '@/features/orders/composables/useOrdersQueries'
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import { normalizeApiError } from '@/shared/api/errors'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'
import { CUSTOMERS_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'
import type { Customer } from '@/types'

const route = useRoute()
const router = useRouter()
const { permissions } = useAuth()
const listUiStore = useListUiStateStore()
const listModule = 'customers' as const
const isSyncingFromRoute = ref(false)

const customerSubmitError = ref('')
const customerFieldErrors = ref<Record<string, string>>({})
const customerServerFieldErrors = ref<Record<string, string>>({})
const customerClientFieldErrors = ref<Record<string, string>>({})
const pendingDeleteCustomerId = ref<number | null>(null)
const persistedDetailCustomer = ref<Customer | null>(null)
const customerForm = ref(createEmptyCustomerDialogForm())
const touchedCustomerFields = reactive<Record<CustomerFormField, boolean>>({
  first_name: false,
  middle_name: false,
  last_name: false,
  phone: false,
  email: false,
  'address.country': false,
  'address.city': false,
  'address.postal_code': false,
  'address.address_line1': false,
  'address.address_line2': false,
})

const canCreateCustomers = computed(() => permissions.value.includes('customers.create'))
const canEditCustomers = computed(() => permissions.value.includes('customers.update'))
const canDeleteCustomers = computed(() => permissions.value.includes('customers.delete'))
const canViewCustomers = computed(() => permissions.value.includes('customers.view'))
const canViewOrders = computed(() => permissions.value.includes('orders.view'))

const page = computed({
  get: () => listUiStore.modules[listModule].page,
  set: (value: number) => listUiStore.setState(listModule, { page: value }),
})
const perPage = computed({
  get: () => listUiStore.modules[listModule].per_page,
  set: (value: number) => listUiStore.setState(listModule, { per_page: value }),
})
const searchInput = computed({
  get: () => listUiStore.modules[listModule].q,
  set: (value: string) => listUiStore.setState(listModule, { q: value }),
})
const isCreateRoute = computed(() => route.name === 'customer-create')
const isDetailRoute = computed(() => route.name === 'customer-detail')
const isEditRoute = computed(() => route.name === 'customer-edit')

const debouncedSearch = useDebouncedRef(searchInput)

const customersQuery = useCustomersQuery(
  computed(() => ({
    page: page.value,
    per_page: perPage.value,
    q: debouncedSearch.value || undefined,
  })),
  {
    enabled: computed(() => canViewCustomers.value && !isCreateRoute.value && !isEditRoute.value),
  },
)
const createCustomerMutation = useCreateCustomerMutation()
const updateCustomerMutation = useUpdateCustomerMutation()
const deleteCustomerMutation = useDeleteCustomerMutation()
const activeCustomerId = computed(() => {
  if (!isDetailRoute.value && !isEditRoute.value) {
    return 0
  }

  return Number(route.params.id)
})

const detailCustomerQuery = useCustomerQuery(activeCustomerId)
const detailCustomer = computed(() => detailCustomerQuery.data.value?.data ?? null)
const detailCustomerForDialog = computed(
  () => detailCustomer.value ?? persistedDetailCustomer.value,
)
const orderHistoryQuery = useOrdersQuery(
  computed(() => ({
    page: 1,
    per_page: 5,
    customer_id: detailCustomerForDialog.value?.id,
  })),
  {
    enabled: computed(
      () =>
        canViewOrders.value &&
        isDetailRoute.value &&
        detailCustomerForDialog.value != null &&
        detailCustomerForDialog.value.id > 0,
    ),
  },
)

const customers = computed(() => customersQuery.data.value?.data ?? [])
const meta = computed(() => customersQuery.data.value?.meta)
const orderHistory = computed(() => orderHistoryQuery.data.value?.data ?? [])
const isInitialLoading = useInitialLoadingGate(customersQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && customersQuery.isFetching.value)
const isDetailDialogLoading = computed(
  () => (isDetailRoute.value || isEditRoute.value) && detailCustomerQuery.isLoading.value,
)
const isCustomerFormPending = computed(
  () => createCustomerMutation.isPending.value || updateCustomerMutation.isPending.value,
)

const customerDisplayName = (customer: Customer | null) => {
  if (!customer) {
    return ''
  }

  return (
    customer.name ||
    [customer.first_name, customer.middle_name, customer.last_name].filter(Boolean).join(' ')
  )
}

const isAddressField = (field: CustomerFormField): boolean => field.startsWith('address.')

const updateCustomerFieldErrors = () => {
  customerFieldErrors.value = {
    ...customerServerFieldErrors.value,
    ...customerClientFieldErrors.value,
  }
}

const getCustomerAddressLines = (customer: Customer | null): string[] => {
  const address = customer?.address

  if (!address) {
    return []
  }

  return [
    address.address_line1,
    address.address_line2,
    [address.city, address.postal_code].filter(Boolean).join(' '),
    address.country,
  ]
    .filter((value) => value && String(value).trim() !== '')
    .map((value) => String(value))
}

const resetCustomerFieldValidation = () => {
  customerFieldErrors.value = {}
  customerServerFieldErrors.value = {}
  customerClientFieldErrors.value = {}

  CUSTOMER_FORM_FIELDS_TO_VALIDATE.forEach((field) => {
    touchedCustomerFields[field] = false
  })
}

const setCustomerClientFieldError = (field: CustomerFormField, message: string | null) => {
  if (message) {
    customerClientFieldErrors.value[field] = message
  } else {
    delete customerClientFieldErrors.value[field]
  }

  updateCustomerFieldErrors()
}

const clearCustomerServerFieldError = (field: CustomerFormField) => {
  if (!customerServerFieldErrors.value[field]) {
    return
  }

  delete customerServerFieldErrors.value[field]
  customerServerFieldErrors.value = { ...customerServerFieldErrors.value }
  updateCustomerFieldErrors()
}

const validateCustomerField = (field: CustomerFormField) => {
  const message = validateCustomerDialogField(customerForm.value, field)
  setCustomerClientFieldError(field, message)
}
const onCustomerFieldBlur = (field: CustomerFormField) => {
  touchedCustomerFields[field] = true

  if (isAddressField(field)) {
    validateCustomerField(field)
    return
  }

  validateCustomerField(field)
}

const onCustomerFieldInput = (field: CustomerFormField) => {
  clearCustomerServerFieldError(field)

  if (isAddressField(field)) {
    if (touchedCustomerFields[field]) {
      validateCustomerField(field)
    }

    return
  }

  if (!touchedCustomerFields[field]) {
    return
  }

  validateCustomerField(field)
}

watch(
  detailCustomer,
  (customer) => {
    if (customer) {
      persistedDetailCustomer.value = customer
    }
  },
  { immediate: true },
)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>

    if (!listUiStore.hasRelevantQuery(normalizedQuery, CUSTOMERS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, CUSTOMERS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, CUSTOMERS_LIST_FIELDS)
    void nextTick().then(() => {
      isSyncingFromRoute.value = false
    })
  },
  { immediate: true },
)

watch(debouncedSearch, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch(perPage, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch([debouncedSearch, page, perPage], () => {
  if (isSyncingFromRoute.value) {
    return
  }

  const nextQuery = {
    ...listUiStore.toQuery(listModule, CUSTOMERS_LIST_FIELDS),
    ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
  }
  const normalizedCurrentQuery = listUiStore.normalizeQuery(
    listModule,
    route.query as Record<string, unknown>,
    CUSTOMERS_LIST_FIELDS,
  )

  if (JSON.stringify(nextQuery) === JSON.stringify(normalizedCurrentQuery)) {
    return
  }

  void router.replace({ query: nextQuery })
})

watch(
  [isCreateRoute, isEditRoute, detailCustomerForDialog],
  () => {
    resetCustomerFieldValidation()
    customerSubmitError.value = ''

    if (isCreateRoute.value) {
      customerForm.value = createEmptyCustomerDialogForm()
      return
    }

    if (isEditRoute.value && detailCustomerForDialog.value) {
      customerForm.value = {
        first_name: detailCustomerForDialog.value.first_name ?? '',
        middle_name: detailCustomerForDialog.value.middle_name ?? '',
        last_name: detailCustomerForDialog.value.last_name ?? '',
        phone: detailCustomerForDialog.value.phone ?? '',
        email: detailCustomerForDialog.value.email ?? '',
        address: {
          country:
            detailCustomerForDialog.value.address?.country ?? DEFAULT_CUSTOMER_ADDRESS_COUNTRY,
          city: detailCustomerForDialog.value.address?.city ?? '',
          postal_code: detailCustomerForDialog.value.address?.postal_code ?? '',
          address_line1: detailCustomerForDialog.value.address?.address_line1 ?? '',
          address_line2: detailCustomerForDialog.value.address?.address_line2 ?? '',
        },
      }
    }
  },
  { immediate: true },
)

const mapFieldErrors = (errors?: Record<string, string[]>) => {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages?.[0] ?? 'Invalid value']),
  )
}

const buildCustomerPayload = (form: CustomerDialogForm): CustomerUpsertPayload => {
  return {
    first_name: form.first_name.trim(),
    middle_name: form.middle_name.trim() || null,
    last_name: form.last_name.trim(),
    phone: form.phone.trim(),
    email: form.email.trim().toLowerCase(),
    address: {
      country: form.address.country.trim(),
      city: form.address.city.trim(),
      postal_code: form.address.postal_code.trim(),
      address_line1: form.address.address_line1.trim(),
      address_line2: form.address.address_line2.trim() || null,
    },
  }
}

const resetFilters = () => {
  searchInput.value = ''
  perPage.value = 15
  page.value = 1
}

const openCreateDialog = async () => {
  await router.push({
    name: 'customer-create',
    query: route.query,
  })
}

const openCustomerDetail = async (customerId: number) => {
  await router.push({
    name: 'customer-detail',
    params: { id: String(customerId) },
    query: route.query,
  })
}

const openCustomerEdit = async (customerId: number) => {
  await router.push({
    name: 'customer-edit',
    params: { id: String(customerId) },
    query: route.query,
  })
}

const closeCustomerDetailDialog = async () => {
  await router.push({
    name: 'customers',
    query: route.query,
  })
}

const closeCustomerFormDialog = async () => {
  if (isEditRoute.value && activeCustomerId.value > 0) {
    await router.push({
      name: 'customer-detail',
      params: { id: String(activeCustomerId.value) },
      query: route.query,
    })
    return
  }

  await router.push({
    name: 'customers',
    query: route.query,
  })
}

const openDeleteDialog = (customerId: number) => {
  customerSubmitError.value = ''
  pendingDeleteCustomerId.value = customerId
}

const isCustomerDetailDialogOpen = computed({
  get: () => isDetailRoute.value,
  set: (value: boolean) => {
    if (!value) {
      void closeCustomerDetailDialog()
    }
  },
})

const isCustomerFormDialogOpen = computed({
  get: () => isCreateRoute.value || isEditRoute.value,
  set: (value: boolean) => {
    if (!value) {
      void closeCustomerFormDialog()
    }
  },
})

const deleteDialogOpen = computed({
  get: () => pendingDeleteCustomerId.value != null,
  set: (value: boolean) => {
    if (!value) {
      pendingDeleteCustomerId.value = null
    }
  },
})

const submitCustomerForm = async () => {
  customerSubmitError.value = ''

  const clientErrors = validateCustomerDialogForm(customerForm.value)
  if (Object.keys(clientErrors).length > 0) {
    customerClientFieldErrors.value = clientErrors
    updateCustomerFieldErrors()
    CUSTOMER_FORM_FIELDS_TO_VALIDATE.forEach((field) => {
      touchedCustomerFields[field] = true
    })
    return
  }

  customerClientFieldErrors.value = {}
  customerServerFieldErrors.value = {}
  customerFieldErrors.value = {}
  const payload = buildCustomerPayload(customerForm.value)

  try {
    if (isEditRoute.value && activeCustomerId.value > 0) {
      const response = await updateCustomerMutation.mutateAsync({
        id: activeCustomerId.value,
        payload,
      })

      toast.success('Customer updated successfully.')
      await router.push({
        name: 'customer-detail',
        params: { id: String(response.data.id) },
        query: route.query,
      })
      return
    }

    const response = await createCustomerMutation.mutateAsync(payload)
    toast.success('Customer created successfully.')
    await router.push({
      name: 'customer-detail',
      params: { id: String(response.data.id) },
      query: route.query,
    })
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    customerServerFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    updateCustomerFieldErrors()
    customerSubmitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const onDeleteCustomer = async () => {
  if (pendingDeleteCustomerId.value == null) {
    return
  }

  customerSubmitError.value = ''

  try {
    const deletedCustomerId = pendingDeleteCustomerId.value
    await deleteCustomerMutation.mutateAsync(deletedCustomerId)
    pendingDeleteCustomerId.value = null
    toast.success('Customer deleted successfully.')

    if (activeCustomerId.value === deletedCustomerId) {
      await router.push({
        name: 'customers',
        query: route.query,
      })
    }
  } catch (error: unknown) {
    customerSubmitError.value = normalizeApiError(error).message
  }
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Customers"
      description="Search customer records, maintain contact details, and inspect related orders without leaving the workspace."
    >
      <template #actions>
        <Button
          v-if="canCreateCustomers"
          size="sm"
          data-test="customers-open-create"
          @click="openCreateDialog"
        >
          <Plus data-icon="inline-start" />
          Add Customer
        </Button>
      </template>
    </PageHeader>

    <Card class="gap-0">
      <CardHeader class="pb-4">
        <CardTitle class="text-base">Search Customers</CardTitle>
        <CardDescription>
          Search by name, email, or phone and keep the table synced to the URL.
        </CardDescription>
      </CardHeader>
      <CardContent class="flex flex-col gap-3">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
          <div class="relative w-full xl:min-w-[360px]">
            <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
            <Input
              v-model="searchInput"
              class="pl-9"
              placeholder="Search by customer name, email, or phone…"
              autocomplete="off"
              spellcheck="false"
              aria-label="Search customers"
              data-test="customers-search"
            />
          </div>

          <div class="flex items-center justify-end gap-2">
            <Button variant="outline" @click="resetFilters">Reset</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="customersQuery.error.value" message="Failed to load customers." />
    <ApiErrorAlert v-if="customerSubmitError" :message="customerSubmitError" />

    <EmptyStateCard
      v-if="!customersQuery.isLoading.value && customers.length === 0"
      title="No customers"
      description="No customer records match the current filters."
    />

    <Card v-else class="pb-3">
      <CardContent>
        <CustomersDataTable
          v-if="meta"
          :rows="customers"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total-rows="meta.total"
          :per-page="meta.per_page"
          :can-manage-customers="canEditCustomers || canDeleteCustomers"
          :can-edit-customers="canEditCustomers"
          :can-delete-customers="canDeleteCustomers"
          @view="openCustomerDetail"
          @edit="openCustomerEdit"
          @delete="openDeleteDialog"
          @update:page="(nextPage) => (page = nextPage)"
          @update:per-page="(nextPerPage) => (perPage = nextPerPage)"
        />
      </CardContent>
    </Card>

    <Dialog v-model:open="isCustomerDetailDialogOpen">
      <DialogContent class="sm:max-w-4xl" data-test="customers-detail-dialog">
        <DialogHeader>
          <DialogTitle>Customer Detail</DialogTitle>
          <DialogDescription>
            Review the customer contact profile and recent order history.
          </DialogDescription>
        </DialogHeader>

        <ApiErrorAlert
          v-if="detailCustomerQuery.error.value"
          message="Unable to load customer details."
        />

        <div v-else-if="isDetailDialogLoading" class="text-muted-foreground text-sm">
          Loading customer details…
        </div>

        <div v-else-if="detailCustomerForDialog" class="space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
              <h3 class="text-lg font-semibold">
                {{
                  customerDisplayName(detailCustomerForDialog) ||
                  `Customer #${detailCustomerForDialog.id}`
                }}
              </h3>
              <p class="text-muted-foreground text-sm">Customer profile</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Button
                v-if="canEditCustomers"
                variant="outline"
                size="sm"
                data-test="customers-detail-edit"
                @click="openCustomerEdit(detailCustomerForDialog.id)"
              >
                Edit
              </Button>
              <Button
                v-if="canDeleteCustomers"
                variant="outline"
                size="sm"
                data-test="customers-detail-delete"
                @click="openDeleteDialog(detailCustomerForDialog.id)"
              >
                Delete
              </Button>
            </div>
          </div>

          <div class="grid gap-3 lg:grid-cols-[315px_minmax(0,1fr)]">
            <Card class="gap-0">
              <CardHeader class="pb-3 gap-0">
                <CardTitle class="text-base">Contact Summary</CardTitle>
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <p>
                  <span class="font-medium">First name:</span>
                  {{ detailCustomerForDialog.first_name ?? '-' }}
                </p>
                <p>
                  <span class="font-medium">Middle name:</span>
                  {{ detailCustomerForDialog.middle_name ?? '-' }}
                </p>
                <p>
                  <span class="font-medium">Last name:</span>
                  {{ detailCustomerForDialog.last_name ?? '-' }}
                </p>
                <p data-test="customers-detail-phone">
                  <span class="font-medium">Phone:</span> {{ detailCustomerForDialog.phone ?? '-' }}
                </p>
                <p class="break-all" data-test="customers-detail-email">
                  <span class="font-medium">Email:</span> {{ detailCustomerForDialog.email ?? '-' }}
                </p>
                <div data-test="customers-detail-address">
                  <span class="font-medium">Address:</span>
                  <div
                    v-if="getCustomerAddressLines(detailCustomerForDialog).length > 0"
                    class="mt-1 space-y-0.5"
                  >
                    <p
                      v-for="(line, index) in getCustomerAddressLines(detailCustomerForDialog)"
                      :key="`${detailCustomerForDialog.id}-address-${index}`"
                      data-test="customers-detail-address-line"
                    >
                      {{ line }}
                    </p>
                  </div>
                  <span v-else> - </span>
                </div>
              </CardContent>
            </Card>

            <Card class="gap-0">
              <CardHeader class="pb-3">
                <CardTitle class="text-base">Order History</CardTitle>
                <CardDescription> Read-only order context tied to this customer. </CardDescription>
              </CardHeader>
              <CardContent class="space-y-3">
                <p v-if="!canViewOrders" class="text-muted-foreground text-sm">
                  You do not have access to order history for this customer.
                </p>

                <div
                  v-else-if="orderHistoryQuery.isLoading.value"
                  class="text-muted-foreground text-sm"
                >
                  Loading related orders…
                </div>

                <ApiErrorAlert
                  v-else-if="orderHistoryQuery.error.value"
                  message="Unable to load customer order history."
                />

                <div v-else-if="orderHistory.length === 0" class="text-muted-foreground text-sm">
                  No orders are currently associated with this customer.
                </div>

                <OverlayScrollViewport
                  v-else
                  data-test="customers-order-history-scroll"
                  class="max-h-[289px] rounded-md border"
                >
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Reference</TableHead>
                        <TableHead class="text-center">Status</TableHead>
                        <TableHead class="text-center">Created</TableHead>
                        <TableHead class="text-center">Amount</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow v-for="order in orderHistory" :key="order.id">
                        <TableCell>
                          <RouterLink
                            :to="`/orders/${order.id}`"
                            class="font-medium hover:underline"
                          >
                            {{ order.reference }}
                          </RouterLink>
                        </TableCell>
                        <TableCell class="text-center">
                          <StatusBadge :status="order.current_status" />
                        </TableCell>
                        <TableCell class="text-center tabular-nums">
                          {{ formatDateTime(order.created_at) }}
                        </TableCell>
                        <TableCell class="text-center font-medium tabular-nums">
                          {{ formatCurrency(order.total_amount) }}
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </OverlayScrollViewport>
              </CardContent>
            </Card>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="isCustomerFormDialogOpen">
      <DialogContent class="sm:max-w-2xl" data-test="customers-form-dialog">
        <DialogHeader>
          <DialogTitle>{{ isEditRoute ? 'Edit Customer' : 'Create Customer' }}</DialogTitle>
          <DialogDescription>
            {{
              isEditRoute
                ? 'Update the contact record and keep the customer history intact.'
                : 'Add a customer record so future orders and shipments can reuse it.'
            }}
          </DialogDescription>
        </DialogHeader>

        <ApiErrorAlert v-if="customerSubmitError" :message="customerSubmitError" />

        <CustomerForm
          v-model="customerForm"
          :field-errors="customerFieldErrors"
          :mode="isEditRoute ? 'edit' : 'create'"
          :pending="isCustomerFormPending"
          @cancel="isCustomerFormDialogOpen = false"
          @field-blur="onCustomerFieldBlur"
          @field-input="onCustomerFieldInput"
          @submit="submitCustomerForm"
        />
      </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete customer</AlertDialogTitle>
          <AlertDialogDescription>
            This removes the customer record from the workspace. Existing operational history
            remains in the backend.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <ApiErrorAlert v-if="customerSubmitError" :message="customerSubmitError" />
        <AlertDialogFooter>
          <AlertDialogCancel :disabled="deleteCustomerMutation.isPending.value">
            Cancel
          </AlertDialogCancel>
          <Button
            variant="destructive"
            :disabled="deleteCustomerMutation.isPending.value"
            data-test="customers-delete-confirm"
            @click="onDeleteCustomer"
          >
            {{ deleteCustomerMutation.isPending.value ? 'Deleting...' : 'Delete customer' }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </section>
</template>
