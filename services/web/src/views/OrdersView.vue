<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Plus, Search } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
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
import { useSalesChannelsQuery } from '@/features/sales-channels/composables/useSalesChannelsQuery'
import { BULGARIA_COURIER_OPTIONS } from '@/features/shipments/constants/couriers'
import {
  useCreateShipmentMutation,
  useShipmentsQuery,
} from '@/features/shipments/composables/useShipmentsQueries'
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
import type { Order, Shipment } from '@/types'
import type { CreateShipmentPayload } from '@/features/shipments/types'
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import { isPositiveIntegerString } from '@/lib/utils'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import { normalizeApiError } from '@/shared/api/errors'
import { ORDERS_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'
import {
  ApiErrorAlert,
  CourierComboboxInput,
  DatePickerInput,
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
const validStatuses = new Set<string>(ORDER_STATUS_OPTIONS)

const pendingAction = ref<{ type: PendingActionType; orderId: number } | null>(null)
const mutationError = ref('')
const createFieldErrors = ref<Record<string, string>>({})
const createSubmitError = ref('')
const editFieldErrors = ref<Record<string, string>>({})
const editSubmitError = ref('')
const isSyncingFromRoute = ref(false)
const persistedDetailOrder = ref<Order | null>(null)
const persistedEditOrder = ref<Order | null>(null)
const shipmentOrder = ref<Pick<Order, 'id' | 'reference' | 'current_status'> | null>(null)
const shipmentForm = ref({
  courier: '',
  tracking_number: '',
  shipped_at: '',
})
const shipmentFieldErrors = ref<Record<string, string>>({})
const shipmentSubmitError = ref('')
const salesChannelSearch = ref('')

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

const canCreate = computed(() => permissions.value.includes('orders.create'))
const canEditDraft = computed(() => permissions.value.includes('orders.update'))
const canDeleteDraft = computed(() => permissions.value.includes('orders.delete'))
const canConfirm = computed(() => permissions.value.includes('orders.status.confirm'))
const canReadyToShip = computed(() => permissions.value.includes('orders.status.ready_to_ship'))
const canCancel = computed(() => permissions.value.includes('orders.status.cancel'))
const canCreateShipment = computed(() => permissions.value.includes('shipments.create'))

const customersQuery = useCustomersQuery(
  computed(() => ({
    per_page: 100,
  })),
  {
    allPages: true,
  },
)
const lookupQuery = useOrderCreateLookupQuery()
const salesChannelsQuery = useSalesChannelsQuery(salesChannelSearch)

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
const createShipmentMutation = useCreateShipmentMutation()
const updateMutation = useUpdateOrderMutation()
const isCreateRoute = computed(() => route.name === 'order-create')
const isDetailRoute = computed(() => route.name === 'order-detail')
const isEditRoute = computed(() => route.name === 'order-edit')
const detailOrderId = computed(() => Number(route.params.id))
const detailOrderQuery = useOrderQuery(detailOrderId)
const detailOrder = computed(() => detailOrderQuery.data.value?.data ?? null)
const editOrderId = computed(() => Number(route.params.id))
const editOrderQuery = useOrderQuery(editOrderId)
const editOrder = computed(() => editOrderQuery.data.value?.data ?? null)
const detailOrderForDialog = computed(() => detailOrder.value ?? persistedDetailOrder.value)
const editOrderForDialog = computed(() => editOrder.value ?? persistedEditOrder.value)
const isEditDraftOrder = computed(() => editOrderForDialog.value?.current_status === 'draft')
const shouldLoadExistingShipment = computed(() => shipmentOrder.value?.current_status === 'shipped')
const existingShipmentByOrderQuery = useShipmentsQuery(
  computed(() => ({
    order_id: shipmentOrder.value?.id,
    per_page: 1,
  })),
  {
    enabled: shouldLoadExistingShipment,
  },
)
const existingShipment = computed<Shipment | null>(() => {
  const orderId = shipmentOrder.value?.id
  if (!orderId) {
    return null
  }

  return (
    (existingShipmentByOrderQuery.data.value?.data ?? []).find(
      (shipment) => shipment.order_id === orderId,
    ) ?? null
  )
})
const isExistingShipmentLoading = computed(
  () => shouldLoadExistingShipment.value && existingShipmentByOrderQuery.isFetching.value,
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
  () =>
    customersQuery.isLoading.value ||
    lookupQuery.isLoading.value ||
    salesChannelsQuery.isLoading.value,
)
const isEditDialogLoading = computed(
  () =>
    editOrderQuery.isLoading.value ||
    customersQuery.isLoading.value ||
    lookupQuery.isLoading.value ||
    salesChannelsQuery.isLoading.value,
)
const isDetailDialogLoading = computed(() => detailOrderQuery.isLoading.value)
const shouldShowDetailDialogLoading = computed(
  () =>
    isDetailDialogLoading.value || (!detailOrderQuery.error.value && !detailOrderForDialog.value),
)

const orders = computed(() => ordersQuery.data.value?.data ?? [])
const meta = computed(() => ordersQuery.data.value?.meta)

const customerNameById = computed(() => {
  const entries = (customersQuery.data.value?.data ?? []).map(
    (customer) => [customer.id, customer.name] as const,
  )
  return new Map<number, string>(entries)
})

const salesChannels = computed(() => salesChannelsQuery.data.value?.data ?? [])

const salesChannelNameById = computed(() => {
  const entries = salesChannels.value.map((channel) => [channel.id, channel.name] as const)
  return new Map<number, string>(entries)
})

const orderFormLookups = computed(() => {
  if (!lookupQuery.data.value?.data && salesChannels.value.length === 0) {
    return null
  }

  return {
    sales_channels: salesChannels.value,
    products: lookupQuery.data.value?.data?.products ?? [],
  }
})

const tableRows = computed<OrdersTableRow[]>(() =>
  orders.value.map((order) => ({
    ...order,
    customer_name:
      order.customer_name ??
      customerNameById.value.get(order.customer_id) ??
      `Customer #${order.customer_id}`,
    sales_channel_name:
      order.sales_channel_name ??
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

const isShipmentDialogOpen = computed({
  get: () => shipmentOrder.value != null,
  set: (value: boolean) => {
    if (!value) {
      shipmentOrder.value = null
    }
  },
})

watch(
  detailOrder,
  (order) => {
    if (order) {
      persistedDetailOrder.value = order
    }
  },
  { immediate: true },
)

watch(
  editOrder,
  (order) => {
    if (order) {
      persistedEditOrder.value = order
    }
  },
  { immediate: true },
)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>
    if (!listUiStore.hasRelevantQuery(normalizedQuery, ORDERS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, ORDERS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, ORDERS_LIST_FIELDS, {
      status: (value: string) => validStatuses.has(value),
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
        status: (value: string) => validStatuses.has(value),
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
      toast.success('Order moved to confirmed successfully.')
    } else if (type === 'ready') {
      await readyMutation.mutateAsync(orderId)
      toast.success('Order moved to ready_to_ship successfully.')
    } else if (type === 'cancel') {
      await cancelMutation.mutateAsync(orderId)
      toast.success('Order cancelled successfully.')
    } else {
      await deleteMutation.mutateAsync(orderId)
      toast.success('Draft order deleted successfully.')
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

const canOpenShipmentForStatus = (status: string) => ['ready_to_ship', 'shipped'].includes(status)

const resetShipmentForm = () => {
  shipmentForm.value = {
    courier: '',
    tracking_number: '',
    shipped_at: '',
  }
}

const normalizeDateInput = (value?: string | null): string => {
  if (!value) {
    return ''
  }

  const trimmed = value.trim()
  const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})(?:$|T)/)
  if (!match) {
    return ''
  }

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])

  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
    return ''
  }

  const date = new Date(Date.UTC(year, month - 1, day))
  if (
    date.getUTCFullYear() !== year ||
    date.getUTCMonth() !== month - 1 ||
    date.getUTCDate() !== day
  ) {
    return ''
  }

  return `${match[1]}-${match[2]}-${match[3]}`
}

watch(
  [shipmentOrder, existingShipment],
  ([order, shipment]) => {
    if (!order || order.current_status !== 'shipped' || !shipment) {
      return
    }

    if (shipmentForm.value.courier.trim().length === 0) {
      shipmentForm.value.courier = shipment.courier
    }

    if (shipmentForm.value.tracking_number.trim().length === 0 && shipment.tracking_number) {
      shipmentForm.value.tracking_number = shipment.tracking_number
    }

    if (shipmentForm.value.shipped_at.length === 0) {
      const shippedAt = normalizeDateInput(shipment.shipped_at)
      if (shippedAt) {
        shipmentForm.value.shipped_at = shippedAt
      }
    }
  },
  { immediate: true },
)

const openShipmentDialog = (order: Pick<Order, 'id' | 'reference' | 'current_status'>) => {
  if (!canCreateShipment.value || !canOpenShipmentForStatus(order.current_status)) {
    return
  }

  shipmentSubmitError.value = ''
  shipmentFieldErrors.value = {}
  resetShipmentForm()
  shipmentOrder.value = order
}

const openShipmentDialogByOrderId = (orderId: number) => {
  const order = tableRows.value.find((row) => row.id === orderId)
  if (!order) {
    return
  }

  openShipmentDialog(order)
}

const openShipmentDialogFromDetail = async () => {
  if (!detailOrderForDialog.value) {
    return
  }

  const order = detailOrderForDialog.value
  await closeOrderDialog()
  openShipmentDialog(order)
}

const mapFieldErrors = (errors?: Record<string, string[]>) => {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages?.[0] ?? 'Invalid value']),
  )
}

const onShipmentSubmit = async () => {
  if (!shipmentOrder.value) {
    return
  }

  shipmentSubmitError.value = ''
  shipmentFieldErrors.value = {}

  const normalizedTrackingNumber = shipmentForm.value.tracking_number.trim()
  const normalizedShippedAt = normalizeDateInput(shipmentForm.value.shipped_at)
  const existingTrackingNumber = existingShipment.value?.tracking_number?.trim() ?? ''
  const existingShippedAt = normalizeDateInput(existingShipment.value?.shipped_at)
  const resolvedTrackingNumber = normalizedTrackingNumber || existingTrackingNumber
  const resolvedShippedAt = normalizedShippedAt || existingShippedAt
  const isUpdatingShipment = shipmentOrder.value.current_status === 'shipped'
  const missingExistingShipmentForSafeMerge = isUpdatingShipment && existingShipment.value == null

  const payload: CreateShipmentPayload = {
    courier: shipmentForm.value.courier.trim(),
    ...(resolvedTrackingNumber.length > 0 ? { tracking_number: resolvedTrackingNumber } : {}),
    ...(resolvedShippedAt.length > 0 ? { shipped_at: resolvedShippedAt } : {}),
  }

  if (payload.courier.length === 0) {
    shipmentFieldErrors.value = { courier: 'Courier is required.' }
    return
  }

  if (missingExistingShipmentForSafeMerge) {
    const errors: Record<string, string> = {}
    if (normalizedTrackingNumber.length === 0) {
      errors.tracking_number =
        'Tracking number is required when existing shipment details are unavailable.'
    }
    if (normalizedShippedAt.length === 0) {
      errors.shipped_at = 'Shipped date is required when existing shipment details are unavailable.'
    }

    if (Object.keys(errors).length > 0) {
      shipmentFieldErrors.value = errors
      return
    }
  }

  try {
    await createShipmentMutation.mutateAsync({
      orderId: shipmentOrder.value.id,
      payload,
    })
    toast.success(
      isUpdatingShipment ? 'Shipment updated successfully.' : 'Shipment created successfully.',
    )
    shipmentOrder.value = null
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    shipmentFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    shipmentSubmitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const onCreateSubmit = async (payload: OrderUpsertPayload) => {
  createSubmitError.value = ''
  createFieldErrors.value = {}

  try {
    const response = await createMutation.mutateAsync(payload)
    toast.success('Order created successfully.')
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
    toast.success('Order updated successfully.')
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
          <RouterLink to="/orders/new" data-test="orders-open-create">
            <Plus data-icon="inline-start" />
            New Order
          </RouterLink>
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
              aria-label="Search orders by reference or notes"
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
                  v-for="channel in salesChannels"
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
          :can-create-shipment="canCreateShipment"
          @confirm="(id) => openActionDialog('confirm', id)"
          @ready-to-ship="(id) => openActionDialog('ready', id)"
          @cancel="(id) => openActionDialog('cancel', id)"
          @delete="(id) => openActionDialog('delete', id)"
          @create-shipment="openShipmentDialogByOrderId"
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
          v-if="
            !isCreateDialogLoading &&
            (customersQuery.error.value ||
              lookupQuery.error.value ||
              salesChannelsQuery.error.value)
          "
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
          :lookups="orderFormLookups"
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
          v-if="!shouldShowDetailDialogLoading && detailOrderQuery.error.value"
          message="Failed to load this order."
        />
        <div
          v-else-if="shouldShowDetailDialogLoading"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          Loading order...
        </div>
        <template v-else-if="detailOrderForDialog">
          <div class="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>{{ detailOrderForDialog.reference }}</CardTitle>
                <CardDescription
                  >Created {{ formatDateTime(detailOrderForDialog.created_at) }}</CardDescription
                >
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <div class="flex flex-wrap items-center gap-3">
                  <span class="font-medium">Status:</span>
                  <StatusBadge :status="detailOrderForDialog.current_status" />
                  <Button
                    v-if="
                      canCreateShipment &&
                      canOpenShipmentForStatus(detailOrderForDialog.current_status)
                    "
                    size="sm"
                    variant="outline"
                    class="w-fit"
                    :data-test="`order-detail-create-shipment-${detailOrderForDialog.id}`"
                    @click="openShipmentDialogFromDetail"
                  >
                    {{
                      detailOrderForDialog.current_status === 'ready_to_ship'
                        ? 'Create Shipment'
                        : 'Update Shipment'
                    }}
                  </Button>
                </div>
                <p>
                  <span class="font-medium">Customer:</span>
                  {{
                    detailOrderForDialog.customer_name ??
                    customerNameById.get(detailOrderForDialog.customer_id) ??
                    `Customer #${detailOrderForDialog.customer_id}`
                  }}
                </p>
                <p>
                  <span class="font-medium">Sales Channel:</span>
                  {{
                    detailOrderForDialog.sales_channel_name ??
                    salesChannelNameById.get(detailOrderForDialog.sales_channel_id) ??
                    `Channel #${detailOrderForDialog.sales_channel_id}`
                  }}
                </p>
                <p>
                  <span class="font-medium">Total:</span>
                  {{ formatCurrency(detailOrderForDialog.total_amount) }}
                </p>
                <p>
                  <span class="font-medium">Internal notes:</span>
                  {{ detailOrderForDialog.internal_notes ?? '-' }}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Status Timeline</CardTitle>
                <CardDescription>Latest status changes.</CardDescription>
              </CardHeader>
              <CardContent
                v-if="
                  !detailOrderForDialog.status_history ||
                  detailOrderForDialog.status_history.length === 0
                "
              >
                <p class="text-sm text-muted-foreground">No transitions recorded yet.</p>
              </CardContent>
              <CardContent v-else>
                <ul class="space-y-2 text-sm">
                  <li
                    v-for="status in detailOrderForDialog.status_history"
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
                  <TableRow v-for="item in detailOrderForDialog.items ?? []" :key="item.id">
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
            (editOrderQuery.error.value ||
              customersQuery.error.value ||
              lookupQuery.error.value ||
              salesChannelsQuery.error.value)
          "
          message="Failed to load order edit form."
        />
        <div v-else-if="isEditDialogLoading" class="py-8 text-center text-sm text-muted-foreground">
          Loading form...
        </div>
        <template v-else-if="editOrderForDialog">
          <div
            v-if="!isEditDraftOrder"
            class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
          >
            Only draft orders can be edited.
          </div>
          <OrderForm
            mode="edit"
            :initial-order="editOrderForDialog"
            :customers="customersQuery.data.value?.data ?? []"
            :lookups="orderFormLookups"
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

    <Dialog v-model:open="isShipmentDialogOpen">
      <DialogContent class="w-[calc(100vw-1.5rem)] sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>
            {{
              shipmentOrder?.current_status === 'ready_to_ship'
                ? 'Create Shipment'
                : 'Update Shipment'
            }}
          </DialogTitle>
          <DialogDescription>
            {{ shipmentOrder?.reference }} · Capture courier metadata and mark the handoff.
          </DialogDescription>
        </DialogHeader>

        <ApiErrorAlert v-if="shipmentSubmitError" :message="shipmentSubmitError" />
        <ApiErrorAlert
          v-if="shouldLoadExistingShipment && existingShipmentByOrderQuery.error.value"
          message="Unable to preload shipment details. Provide tracking number and shipped date to avoid overwriting existing values."
        />

        <form class="grid gap-4" @submit.prevent="onShipmentSubmit">
          <div class="space-y-2">
            <label class="text-sm font-medium" for="shipment-courier">Courier</label>
            <CourierComboboxInput
              class="w-full"
              input-id="shipment-courier"
              name="shipment_courier"
              data-test="shipment-courier"
              :model-value="shipmentForm.courier"
              :options="BULGARIA_COURIER_OPTIONS"
              placeholder="Select or type courier..."
              aria-label="Select or type courier"
              input-class="w-full"
              @update:model-value="(value) => (shipmentForm.courier = value)"
            />
            <p v-if="shipmentFieldErrors.courier" class="text-xs text-destructive">
              {{ shipmentFieldErrors.courier }}
            </p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium" for="shipment-tracking">Tracking number</label>
            <Input
              id="shipment-tracking"
              v-model="shipmentForm.tracking_number"
              name="shipment_tracking_number"
              autocomplete="off"
              spellcheck="false"
            />
            <p v-if="shipmentFieldErrors.tracking_number" class="text-xs text-destructive">
              {{ shipmentFieldErrors.tracking_number }}
            </p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium" for="shipment-shipped-at">Shipped at</label>
            <DatePickerInput
              :model-value="shipmentForm.shipped_at"
              trigger-id="shipment-shipped-at"
              data-test="shipment-shipped-at"
              button-class="w-full"
              @update:model-value="(value) => (shipmentForm.shipped_at = value)"
            />
            <p v-if="shipmentFieldErrors.shipped_at" class="text-xs text-destructive">
              {{ shipmentFieldErrors.shipped_at }}
            </p>
          </div>

          <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="shipmentOrder = null">Cancel</Button>
            <Button
              type="submit"
              :disabled="createShipmentMutation.isPending.value || isExistingShipmentLoading"
              data-test="shipment-dialog-submit"
            >
              {{
                createShipmentMutation.isPending.value
                  ? 'Saving...'
                  : isExistingShipmentLoading
                    ? 'Loading...'
                    : 'Save Shipment'
              }}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </section>
</template>
