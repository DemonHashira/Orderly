<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useCustomersQuery } from '@/features/customers/composables/useCustomersQueries'
import { useOrderCreateLookupQuery } from '@/features/lookups/composables/useOrderCreateLookupQuery'
import {
  useCancelOrderMutation,
  useConfirmOrderMutation,
  useCreateOrderMutation,
  useDeleteOrderMutation,
  useOrderQuery,
  useOrdersQuery,
  useReadyToShipOrderMutation,
  useUpdateOrderMutation,
} from '@/features/orders/composables/useOrdersQueries'
import OrderForm from '@/features/orders/ui/OrderForm.vue'
import type { OrdersTableRow } from '@/features/orders/ui/orders-table-columns'
import OrdersDataTable from '@/features/orders/ui/OrdersDataTable.vue'
import type { OrderUpsertPayload } from '@/features/orders/types'
import { ORDER_STATUS_OPTIONS } from '@/features/orders/types'
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import { normalizeApiError } from '@/shared/api/errors'
import { ORDERS_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'
import {
  ApiErrorAlert,
  DateRangeFilter,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'

type PendingActionType = 'confirm' | 'ready' | 'cancel' | 'delete'

const { permissions } = useAuth()
const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'orders' as const

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
const debouncedSearch = useDebouncedRef(searchInput)
const selectedStatus = computed<'all' | (typeof ORDER_STATUS_OPTIONS)[number]>({
  get: () =>
    (listUiStore.modules[listModule].status as 'all' | (typeof ORDER_STATUS_OPTIONS)[number]) ??
    'all',
  set: (value) => listUiStore.setState(listModule, { status: value }),
})
const selectedSalesChannelId = computed<string>({
  get: () => listUiStore.modules[listModule].sales_channel_id,
  set: (value) => listUiStore.setState(listModule, { sales_channel_id: value }),
})
const createdFrom = computed<string>({
  get: () => listUiStore.modules[listModule].created_from,
  set: (value) => listUiStore.setState(listModule, { created_from: value }),
})
const createdTo = computed<string>({
  get: () => listUiStore.modules[listModule].created_to,
  set: (value) => listUiStore.setState(listModule, { created_to: value }),
})

const pendingAction = ref<{ type: PendingActionType; orderId: number } | null>(null)
const mutationError = ref('')
const createFieldErrors = ref<Record<string, string>>({})
const createSubmitError = ref('')
const editFieldErrors = ref<Record<string, string>>({})
const editSubmitError = ref('')

const canCreate = computed(() => permissions.value.includes('orders.create'))
const canEditDraft = computed(() => permissions.value.includes('orders.update'))
const canDeleteDraft = computed(() => permissions.value.includes('orders.delete'))
const canConfirm = computed(() => permissions.value.includes('orders.status.confirm'))
const canReadyToShip = computed(() => permissions.value.includes('orders.status.ready_to_ship'))
const canCancel = computed(() => permissions.value.includes('orders.status.cancel'))
const validStatuses = new Set<string>(ORDER_STATUS_OPTIONS)
const isPositiveIntegerString = (value: string) => {
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed > 0
}

const customersQuery = useCustomersQuery(
  computed(() => ({
    per_page: 100,
  })),
  {
    allPages: true,
  },
)
const lookupQuery = useOrderCreateLookupQuery()

const ordersQuery = useOrdersQuery(
  computed(() => ({
    page: page.value,
    per_page: perPage.value,
    q: debouncedSearch.value,
    status: selectedStatus.value === 'all' ? undefined : selectedStatus.value,
    sales_channel_id:
      selectedSalesChannelId.value === 'all' ? undefined : Number(selectedSalesChannelId.value),
    created_from: createdFrom.value || undefined,
    created_to: createdTo.value || undefined,
  })),
)

const confirmMutation = useConfirmOrderMutation()
const readyMutation = useReadyToShipOrderMutation()
const cancelMutation = useCancelOrderMutation()
const deleteMutation = useDeleteOrderMutation()
const createMutation = useCreateOrderMutation()
const updateMutation = useUpdateOrderMutation()

const isSyncingFromRoute = ref(false)

const isCreateRoute = computed(() => route.name === 'order-create')
const isDetailRoute = computed(() => route.name === 'order-detail')
const isEditRoute = computed(() => route.name === 'order-edit')
const detailOrderId = computed(() => Number(route.params.id))
const detailOrderQuery = useOrderQuery(detailOrderId)
const detailOrder = computed(() => detailOrderQuery.data.value?.data ?? null)
const editOrderId = computed(() => Number(route.params.id))
const editOrderQuery = useOrderQuery(editOrderId)
const editOrder = computed(() => editOrderQuery.data.value?.data ?? null)
const isEditDraftOrder = computed(() => editOrder.value?.current_status === 'draft')

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>
    if (!listUiStore.hasRelevantQuery(normalizedQuery, ORDERS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, ORDERS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
      }
      return
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, ORDERS_LIST_FIELDS, {
      status: (value) => validStatuses.has(value),
      sales_channel_id: isPositiveIntegerString,
    })
    void nextTick().then(() => {
      isSyncingFromRoute.value = false
    })
  },
  { immediate: true },
)

watch([debouncedSearch, selectedStatus, selectedSalesChannelId, createdFrom, createdTo], () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch(perPage, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch(
  [debouncedSearch, selectedStatus, selectedSalesChannelId, createdFrom, createdTo, page, perPage],
  () => {
    if (isSyncingFromRoute.value) {
      return
    }

    const nextQuery = {
      ...listUiStore.toQuery(listModule, ORDERS_LIST_FIELDS),
      ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
    }
    const currentQuery = listUiStore.normalizeQuery(
      listModule,
      route.query as Record<string, unknown>,
      ORDERS_LIST_FIELDS,
      {
        status: (value) => validStatuses.has(value),
        sales_channel_id: isPositiveIntegerString,
      },
    )

    if (JSON.stringify(nextQuery) === JSON.stringify(currentQuery)) {
      return
    }

    void router.replace({
      query: nextQuery,
    })
  },
)

const isActionMutationPending = computed(() => {
  const isPending = (mutation: unknown) => {
    if (!mutation || typeof mutation !== 'object') {
      return false
    }

    const candidate = mutation as { isPending?: { value?: boolean } | boolean }
    if (typeof candidate.isPending === 'boolean') {
      return candidate.isPending
    }
    return Boolean(candidate.isPending?.value)
  }

  return (
    isPending(confirmMutation) ||
    isPending(readyMutation) ||
    isPending(cancelMutation) ||
    isPending(deleteMutation)
  )
})

const isInitialLoading = useInitialLoadingGate(ordersQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && ordersQuery.isFetching.value)
const isCreateDialogLoading = computed(
  () => customersQuery.isLoading.value || lookupQuery.isLoading.value,
)
const isEditDialogLoading = computed(
  () =>
    editOrderQuery.isLoading.value || customersQuery.isLoading.value || lookupQuery.isLoading.value,
)
const isDetailDialogLoading = computed(() => detailOrderQuery.isLoading.value)

const orders = computed(() => ordersQuery.data.value?.data ?? [])
const meta = computed(() => ordersQuery.data.value?.meta)

const customerNameById = computed(() => {
  const entries = (customersQuery.data.value?.data ?? []).map(
    (customer) => [customer.id, customer.name] as const,
  )
  return new Map<number, string>(entries)
})

const salesChannelNameById = computed(() => {
  const entries = (lookupQuery.data.value?.data?.sales_channels ?? []).map(
    (channel) => [channel.id, channel.name] as const,
  )
  return new Map<number, string>(entries)
})

const tableRows = computed<OrdersTableRow[]>(() =>
  orders.value.map((order) => ({
    ...order,
    customer_name:
      customerNameById.value.get(order.customer_id) ?? `Customer #${order.customer_id}`,
    sales_channel_name:
      salesChannelNameById.value.get(order.sales_channel_id) ??
      `Channel #${order.sales_channel_id}`,
  })),
)

const confirmDialogOpen = computed({
  get: () => pendingAction.value != null,
  set: (value: boolean) => {
    if (!value) {
      pendingAction.value = null
    }
  },
})

const actionDialogCopy = computed(() => {
  if (!pendingAction.value) {
    return {
      title: '',
      description: '',
      confirmLabel: '',
    }
  }

  if (pendingAction.value.type === 'confirm') {
    return {
      title: 'Confirm order',
      description: 'This will move the order to confirmed status.',
      confirmLabel: 'Confirm',
    }
  }

  if (pendingAction.value.type === 'ready') {
    return {
      title: 'Mark ready to ship',
      description: 'This will hand off the order to logistics.',
      confirmLabel: 'Mark Ready',
    }
  }

  if (pendingAction.value.type === 'cancel') {
    return {
      title: 'Cancel order',
      description: 'This action cannot be undone.',
      confirmLabel: 'Cancel Order',
    }
  }

  return {
    title: 'Delete draft order',
    description: 'This will permanently delete the draft order.',
    confirmLabel: 'Delete Draft',
  }
})

const openActionDialog = (type: PendingActionType, orderId: number) => {
  mutationError.value = ''
  pendingAction.value = { type, orderId }
}

const onConfirmAction = async () => {
  if (!pendingAction.value) {
    return
  }

  mutationError.value = ''
  const { type, orderId } = pendingAction.value

  try {
    if (type === 'confirm') {
      await confirmMutation.mutateAsync(orderId)
    } else if (type === 'ready') {
      await readyMutation.mutateAsync(orderId)
    } else if (type === 'cancel') {
      await cancelMutation.mutateAsync(orderId)
    } else {
      await deleteMutation.mutateAsync(orderId)
    }
    pendingAction.value = null
  } catch (error: unknown) {
    mutationError.value = normalizeApiError(error).message
  }
}

const applyPreset = (preset: 'all' | 'last_7' | 'last_30') => {
  if (preset === 'all') {
    createdFrom.value = ''
    createdTo.value = ''
    return
  }

  const end = new Date()
  const start = new Date()
  start.setDate(end.getDate() - (preset === 'last_7' ? 6 : 29))

  const formatDate = (value: Date) => {
    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  createdFrom.value = formatDate(start)
  createdTo.value = formatDate(end)
}

const resetFilters = () => {
  searchInput.value = ''
  selectedStatus.value = 'all'
  selectedSalesChannelId.value = 'all'
  createdFrom.value = ''
  createdTo.value = ''
  page.value = 1
}

const mapFieldErrors = (errors?: Record<string, string[]>) => {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages?.[0] ?? 'Invalid value']),
  )
}

const onCreateSubmit = async (payload: OrderUpsertPayload) => {
  createSubmitError.value = ''
  createFieldErrors.value = {}

  try {
    const response = await createMutation.mutateAsync(payload)
    await router.push(`/orders/${response.data.id}`)
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    createFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    createSubmitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const onEditSubmit = async (payload: OrderUpsertPayload) => {
  if (!editOrder.value) {
    return
  }

  editSubmitError.value = ''
  editFieldErrors.value = {}

  try {
    const response = await updateMutation.mutateAsync({
      id: editOrder.value.id,
      payload,
    })
    await router.push(`/orders/${response.data.id}`)
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    editFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    editSubmitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const closeOrderDialog = async () => {
  await router.push('/orders')
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />

    <PageHeader title="Orders" description="Manage order lifecycle transitions.">
      <template #actions>
        <Button v-if="canCreate" as-child size="sm">
          <RouterLink to="/orders/new">New Order</RouterLink>
        </Button>
      </template>
    </PageHeader>

    <Card class="gap-0">
      <CardHeader class="pb-4">
        <CardTitle class="text-base">Search & Filters</CardTitle>
        <CardDescription>
          Find by reference or notes, then narrow by status, channel, and created range.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
          <div class="relative xl:max-w-xl xl:flex-1">
            <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
            <Input
              v-model="searchInput"
              class="pl-9"
              placeholder="Search orders by reference or internal notes"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 xl:justify-end">
            <Select v-model="selectedStatus">
              <SelectTrigger class="w-47.5">
                <SelectValue placeholder="Filter status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                <SelectItem v-for="status in ORDER_STATUS_OPTIONS" :key="status" :value="status">
                  {{ status.replace(/_/g, ' ') }}
                </SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="selectedSalesChannelId">
              <SelectTrigger class="w-[240px] sm:w-[260px]">
                <SelectValue placeholder="Filter sales channel" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All channels</SelectItem>
                <SelectItem
                  v-for="channel in lookupQuery.data.value?.data?.sales_channels ?? []"
                  :key="channel.id"
                  :value="String(channel.id)"
                >
                  {{ channel.name }}
                </SelectItem>
              </SelectContent>
            </Select>

            <DateRangeFilter
              :from="createdFrom"
              :to="createdTo"
              @update:from="(value) => (createdFrom = value)"
              @update:to="(value) => (createdTo = value)"
              @preset="applyPreset"
            />

            <Button variant="outline" class="min-w-23" @click="resetFilters">Reset</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <ApiErrorAlert
      v-if="ordersQuery.error.value"
      message="Failed to load orders. Please refresh this page."
    />

    <ApiErrorAlert v-if="mutationError" :message="mutationError" />

    <EmptyStateCard
      v-if="!ordersQuery.isLoading.value && tableRows.length === 0"
      title="No orders found"
      description="Try changing your filters or create a new order."
    />

    <Card v-else class="pb-3">
      <CardContent>
        <OrdersDataTable
          v-if="meta"
          :rows="tableRows"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total-rows="meta.total"
          :per-page="meta.per_page"
          :can-confirm="canConfirm"
          :can-ready-to-ship="canReadyToShip"
          :can-cancel="canCancel"
          :can-edit-draft="canEditDraft"
          :can-delete-draft="canDeleteDraft"
          @confirm="(id) => openActionDialog('confirm', id)"
          @ready-to-ship="(id) => openActionDialog('ready', id)"
          @cancel="(id) => openActionDialog('cancel', id)"
          @delete="(id) => openActionDialog('delete', id)"
          @update:page="(nextPage) => (page = nextPage)"
          @update:per-page="(nextPerPage) => (perPage = nextPerPage)"
        />
      </CardContent>
    </Card>

    <AlertDialog v-model:open="confirmDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ actionDialogCopy.title }}</AlertDialogTitle>
          <AlertDialogDescription>{{ actionDialogCopy.description }}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel :disabled="isActionMutationPending">Cancel</AlertDialogCancel>
          <Button :disabled="isActionMutationPending" @click="onConfirmAction">
            {{ actionDialogCopy.confirmLabel }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <Dialog :open="isCreateRoute" @update:open="(open) => !open && closeOrderDialog()">
      <DialogContent
        class="max-h-dvh w-[calc(100vw-1.5rem)] overflow-hidden p-4 sm:max-w-4xl sm:p-6"
      >
        <DialogHeader>
          <DialogTitle>Create Order</DialogTitle>
          <DialogDescription>Create a draft order with items and optional notes.</DialogDescription>
        </DialogHeader>
        <ApiErrorAlert
          v-if="!isCreateDialogLoading && (customersQuery.error.value || lookupQuery.error.value)"
          message="Failed to load order form lookups."
        />
        <div
          v-else-if="isCreateDialogLoading"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          Loading form...
        </div>
        <OrderForm
          v-else
          mode="create"
          :customers="customersQuery.data.value?.data ?? []"
          :lookups="lookupQuery.data.value?.data ?? null"
          :is-submitting="createMutation.isPending.value"
          :api-error="createSubmitError"
          :server-field-errors="createFieldErrors"
          @submit="onCreateSubmit"
          @cancel="closeOrderDialog"
        />
      </DialogContent>
    </Dialog>

    <Dialog :open="isDetailRoute" @update:open="(open) => !open && closeOrderDialog()">
      <DialogContent
        class="max-h-dvh w-[calc(100vw-1.5rem)] overflow-hidden p-4 sm:max-w-4xl sm:p-6"
      >
        <DialogHeader>
          <DialogTitle>Order Detail</DialogTitle>
          <DialogDescription>Inspect order data, items, and status history.</DialogDescription>
        </DialogHeader>
        <ApiErrorAlert
          v-if="!isDetailDialogLoading && detailOrderQuery.error.value"
          message="Failed to load this order."
        />
        <div
          v-else-if="isDetailDialogLoading"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          Loading order...
        </div>
        <template v-else-if="detailOrder">
          <div class="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>{{ detailOrder.reference }}</CardTitle>
                <CardDescription
                  >Created {{ formatDateTime(detailOrder.created_at) }}</CardDescription
                >
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <div class="inline-flex items-center gap-2">
                  <span class="font-medium">Status:</span>
                  <StatusBadge :status="detailOrder.current_status" />
                </div>
                <p><span class="font-medium">Customer ID:</span> {{ detailOrder.customer_id }}</p>
                <p>
                  <span class="font-medium">Sales Channel ID:</span>
                  {{ detailOrder.sales_channel_id }}
                </p>
                <p>
                  <span class="font-medium">Total:</span>
                  {{ formatCurrency(detailOrder.total_amount) }}
                </p>
                <p>
                  <span class="font-medium">Internal notes:</span>
                  {{ detailOrder.internal_notes ?? '-' }}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Status Timeline</CardTitle>
                <CardDescription>Latest status changes.</CardDescription>
              </CardHeader>
              <CardContent
                v-if="!detailOrder.status_history || detailOrder.status_history.length === 0"
              >
                <p class="text-sm text-muted-foreground">No transitions recorded yet.</p>
              </CardContent>
              <CardContent v-else>
                <ul class="space-y-2 text-sm">
                  <li
                    v-for="status in detailOrder.status_history"
                    :key="status.id"
                    class="border-b pb-2 last:border-b-0"
                  >
                    <div class="flex items-center justify-between gap-2">
                      <StatusBadge :status="status.status" />
                      <span class="text-muted-foreground">{{
                        formatDateTime(status.changed_at)
                      }}</span>
                    </div>
                  </li>
                </ul>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Items</CardTitle>
              <CardDescription>Order line items and pricing.</CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Product ID</TableHead>
                    <TableHead class="text-right">Qty</TableHead>
                    <TableHead class="text-right">Unit Price</TableHead>
                    <TableHead class="text-right">Total</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="item in detailOrder.items ?? []" :key="item.id">
                    <TableCell>{{ item.product_id }}</TableCell>
                    <TableCell class="text-right">{{ item.quantity }}</TableCell>
                    <TableCell class="text-right">{{ formatCurrency(item.unit_price) }}</TableCell>
                    <TableCell class="text-right">{{ formatCurrency(item.total_price) }}</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </template>
      </DialogContent>
    </Dialog>

    <Dialog :open="isEditRoute" @update:open="(open) => !open && closeOrderDialog()">
      <DialogContent
        class="max-h-dvh w-[calc(100vw-1.5rem)] overflow-y-auto p-4 sm:max-w-4xl sm:p-6"
      >
        <DialogHeader>
          <DialogTitle>Edit Order</DialogTitle>
          <DialogDescription>Update draft order details and line items.</DialogDescription>
        </DialogHeader>
        <ApiErrorAlert
          v-if="
            !isEditDialogLoading &&
            (editOrderQuery.error.value || customersQuery.error.value || lookupQuery.error.value)
          "
          message="Failed to load order edit form."
        />
        <div v-else-if="isEditDialogLoading" class="py-8 text-center text-sm text-muted-foreground">
          Loading form...
        </div>
        <template v-else-if="editOrder">
          <div
            v-if="!isEditDraftOrder"
            class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
          >
            Only draft orders can be edited.
          </div>
          <OrderForm
            mode="edit"
            :initial-order="editOrder"
            :customers="customersQuery.data.value?.data ?? []"
            :lookups="lookupQuery.data.value?.data ?? null"
            :is-submitting="updateMutation.isPending.value"
            :is-disabled="!isEditDraftOrder"
            :api-error="editSubmitError"
            :server-field-errors="editFieldErrors"
            @submit="onEditSubmit"
            @cancel="closeOrderDialog"
          />
        </template>
      </DialogContent>
    </Dialog>
  </section>
</template>
