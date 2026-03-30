<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowUpRight, Search } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
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
import { formatDateTime } from '@/lib/formatters'
import { useAuth } from '@/features/auth/composables/useAuth'
import { BULGARIA_COURIER_OPTIONS } from '@/features/shipments/constants/couriers'
import {
  useMarkShipmentDeliveredMutation,
  useMarkShipmentReturnedMutation,
  useMarkShipmentUnpaidMutation,
  useShipmentQuery,
  useShipmentsQuery,
} from '@/features/shipments/composables/useShipmentsQueries'
import ShipmentsDataTable from '@/features/shipments/ui/ShipmentsDataTable.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import { normalizeApiError } from '@/shared/api/errors'
import {
  ApiErrorAlert,
  CourierComboboxInput,
  DateRangeFilter,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'
import { useListUiStateStore } from '@/stores/list-ui-state'
import type { ListUiField } from '@/stores/list-ui-state'
import type { Shipment } from '@/types'

type PendingShipmentAction = 'delivered' | 'returned' | 'unpaid'

const SHIPMENTS_LIST_FIELDS: ListUiField[] = [
  'q',
  'status',
  'courier',
  'created_from',
  'created_to',
  'page',
  'per_page',
]
const DELIVERY_FILTER_OPTIONS = ['all', 'delivered', 'pending'] as const

const { permissions } = useAuth()
const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'shipments' as const
const isSyncingFromRoute = ref(false)

const pendingAction = ref<{ type: PendingShipmentAction; shipmentId: number } | null>(null)
const mutationError = ref('')
const persistedDetailShipment = ref<Shipment | null>(null)

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
const deliveredFilter = computed<(typeof DELIVERY_FILTER_OPTIONS)[number]>({
  get: () => {
    const value = listUiStore.modules[listModule].status
    if (value === 'delivered' || value === 'pending') {
      return value
    }
    return 'all'
  },
  set: (value) => listUiStore.setState(listModule, { status: value }),
})
const shippedFrom = computed({
  get: () => listUiStore.modules[listModule].created_from,
  set: (value: string) => listUiStore.setState(listModule, { created_from: value }),
})
const shippedTo = computed({
  get: () => listUiStore.modules[listModule].created_to,
  set: (value: string) => listUiStore.setState(listModule, { created_to: value }),
})
const courierInput = computed({
  get: () => listUiStore.modules[listModule].courier,
  set: (value: string) => listUiStore.setState(listModule, { courier: value }),
})

const debouncedSearch = useDebouncedRef(searchInput)
const debouncedCourier = useDebouncedRef(courierInput)

const canMarkDelivered = computed(() => permissions.value.includes('shipments.outcome.delivered'))
const canMarkReturned = computed(() => permissions.value.includes('shipments.outcome.returned'))
const canMarkUnpaid = computed(() => permissions.value.includes('shipments.outcome.unpaid'))
const canOpenOrdersWorkspace = computed(() => permissions.value.includes('orders.view'))

const shipmentsQuery = useShipmentsQuery(
  computed(() => ({
    page: page.value,
    per_page: perPage.value,
    tracking_number: debouncedSearch.value || undefined,
    courier: debouncedCourier.value || undefined,
    shipped_from: shippedFrom.value || undefined,
    shipped_to: shippedTo.value || undefined,
    delivered: deliveredFilter.value === 'all' ? undefined : deliveredFilter.value === 'delivered',
  })),
)

const isDetailRoute = computed(() => route.name === 'shipment-detail')
const detailShipmentId = computed(() => Number(route.params.id))
const detailShipmentQuery = useShipmentQuery(detailShipmentId)
const detailShipment = computed(() => detailShipmentQuery.data.value?.data ?? null)
const detailShipmentForDialog = computed(
  () => detailShipment.value ?? persistedDetailShipment.value,
)
const isDetailDialogLoading = computed(
  () => isDetailRoute.value && detailShipmentQuery.isLoading.value,
)

watch(
  detailShipment,
  (shipment) => {
    if (shipment) {
      persistedDetailShipment.value = shipment
    }
  },
  { immediate: true },
)

const deliveredMutation = useMarkShipmentDeliveredMutation()
const returnedMutation = useMarkShipmentReturnedMutation()
const unpaidMutation = useMarkShipmentUnpaidMutation()

const shipments = computed(() => shipmentsQuery.data.value?.data ?? [])
const meta = computed(() => shipmentsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(shipmentsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && shipmentsQuery.isFetching.value)

const isShipmentDetailDialogOpen = computed({
  get: () => isDetailRoute.value,
  set: (value: boolean) => {
    if (!value) {
      void closeShipmentDialog()
    }
  },
})

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

  if (pendingAction.value.type === 'delivered') {
    return {
      title: 'Mark delivered',
      description: 'Confirm this shipment was delivered.',
      confirmLabel: 'Delivered',
    }
  }

  if (pendingAction.value.type === 'returned') {
    return {
      title: 'Mark returned',
      description: 'Create a return flow from this shipment.',
      confirmLabel: 'Returned',
    }
  }

  return {
    title: 'Mark unpaid',
    description: 'Mark this shipment as unpaid and open a return flow.',
    confirmLabel: 'Mark Unpaid',
  }
})

const isActionMutationPending = computed(
  () =>
    deliveredMutation.isPending.value ||
    returnedMutation.isPending.value ||
    unpaidMutation.isPending.value,
)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>

    if (!listUiStore.hasRelevantQuery(normalizedQuery, SHIPMENTS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, SHIPMENTS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, SHIPMENTS_LIST_FIELDS, {
      status: (value: string) =>
        DELIVERY_FILTER_OPTIONS.includes(value as (typeof DELIVERY_FILTER_OPTIONS)[number]),
    })
    void nextTick().then(() => {
      isSyncingFromRoute.value = false
    })
  },
  { immediate: true },
)

watch([debouncedSearch, deliveredFilter, shippedFrom, shippedTo, debouncedCourier], () => {
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
  [debouncedSearch, deliveredFilter, shippedFrom, shippedTo, page, perPage, debouncedCourier],
  () => {
    if (isSyncingFromRoute.value) {
      return
    }

    const nextQuery = {
      ...listUiStore.toQuery(listModule, SHIPMENTS_LIST_FIELDS),
      ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
      ...(debouncedCourier.value ? { courier: debouncedCourier.value } : {}),
    }
    const normalizedCurrentQuery = listUiStore.normalizeQuery(
      listModule,
      route.query as Record<string, unknown>,
      SHIPMENTS_LIST_FIELDS,
      {
        status: (value: string) =>
          DELIVERY_FILTER_OPTIONS.includes(value as (typeof DELIVERY_FILTER_OPTIONS)[number]),
      },
    )

    if (JSON.stringify(nextQuery) === JSON.stringify(normalizedCurrentQuery)) {
      return
    }

    void router.replace({ query: nextQuery })
  },
)

const openActionDialog = (type: PendingShipmentAction, shipmentId: number) => {
  mutationError.value = ''
  pendingAction.value = { type, shipmentId }
}

const onConfirmAction = async () => {
  if (!pendingAction.value) {
    return
  }

  mutationError.value = ''

  try {
    if (pendingAction.value.type === 'delivered') {
      await deliveredMutation.mutateAsync(pendingAction.value.shipmentId)
      toast.success('Shipment marked as delivered.')
    } else if (pendingAction.value.type === 'returned') {
      await returnedMutation.mutateAsync(pendingAction.value.shipmentId)
      toast.success('Shipment marked as returned and return flow opened.')
    } else {
      await unpaidMutation.mutateAsync(pendingAction.value.shipmentId)
      toast.success('Shipment marked as unpaid and return flow opened.')
    }

    pendingAction.value = null
  } catch (error: unknown) {
    mutationError.value = normalizeApiError(error).message
  }
}

const applyPreset = (preset: 'all' | 'last_7' | 'last_30') => {
  if (preset === 'all') {
    shippedFrom.value = ''
    shippedTo.value = ''
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

  shippedFrom.value = formatDate(start)
  shippedTo.value = formatDate(end)
}

const resetFilters = () => {
  searchInput.value = ''
  courierInput.value = ''
  deliveredFilter.value = 'all'
  shippedFrom.value = ''
  shippedTo.value = ''
  perPage.value = 15
  page.value = 1
}

const statusForShipment = (shipmentStatus?: string) => shipmentStatus ?? 'shipped'

const closeShipmentDialog = async () => {
  await router.push({
    path: '/shipments',
    query: route.query,
  })
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Shipments" description="Track deliveries and manage shipment outcomes.">
      <template #actions>
        <Button v-if="canOpenOrdersWorkspace" as-child variant="outline" size="sm">
          <RouterLink
            :to="{ path: '/orders', query: { status: 'ready_to_ship' } }"
            data-test="shipments-open-ready-orders"
          >
            <ArrowUpRight data-icon="inline-start" />
            Open Ready Orders
          </RouterLink>
        </Button>
      </template>
    </PageHeader>

    <Card class="gap-0">
      <CardHeader class="pb-4">
        <CardTitle class="text-base">Search & Filters</CardTitle>
        <CardDescription>
          Filter by tracking/courier, shipped range, and delivery state.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
          <div class="relative w-full xl:min-w-[360px]">
            <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
            <Input
              v-model="searchInput"
              class="pl-9"
              placeholder="Search by tracking number..."
              name="shipment_tracking_search"
              autocomplete="off"
              spellcheck="false"
              aria-label="Search shipments by tracking number"
              data-test="shipments-search"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 xl:justify-end">
            <CourierComboboxInput
              class="w-[190px] shrink-0"
              :model-value="courierInput"
              :options="BULGARIA_COURIER_OPTIONS"
              name="shipment_courier_filter"
              placeholder="Courier..."
              aria-label="Filter shipments by courier"
              data-test="shipments-courier-filter"
              input-class="w-full"
              @update:model-value="(value) => (courierInput = value)"
            />

            <DateRangeFilter
              class="shrink-0"
              :from="shippedFrom"
              :to="shippedTo"
              @update:from="(value) => (shippedFrom = value)"
              @update:to="(value) => (shippedTo = value)"
              @preset="applyPreset"
            />

            <Button variant="outline" class="min-w-23 shrink-0" @click="resetFilters">Reset</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="shipmentsQuery.error.value" message="Failed to load shipments." />
    <ApiErrorAlert v-if="mutationError" :message="mutationError" />

    <EmptyStateCard
      v-if="!shipmentsQuery.isLoading.value && shipments.length === 0"
      title="No shipments"
      description="No shipment data matches the current filters."
    />

    <Card v-else class="pb-3">
      <CardContent>
        <ShipmentsDataTable
          v-if="meta"
          :rows="shipments"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total-rows="meta.total"
          :per-page="meta.per_page"
          :can-mark-delivered="canMarkDelivered"
          :can-mark-returned="canMarkReturned"
          :can-mark-unpaid="canMarkUnpaid"
          @mark-delivered="(id) => openActionDialog('delivered', id)"
          @mark-returned="(id) => openActionDialog('returned', id)"
          @mark-unpaid="(id) => openActionDialog('unpaid', id)"
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
          <Button
            :disabled="isActionMutationPending"
            data-test="shipments-confirm-action"
            @click="onConfirmAction"
          >
            {{ actionDialogCopy.confirmLabel }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <Dialog
      :open="isShipmentDetailDialogOpen"
      @update:open="(open) => !open && closeShipmentDialog()"
    >
      <DialogContent
        class="max-h-dvh w-[calc(100vw-1.5rem)] overflow-y-auto p-4 sm:max-w-3xl sm:p-6"
        data-test="shipment-detail-dialog"
      >
        <DialogHeader>
          <DialogTitle>Shipment Detail</DialogTitle>
          <DialogDescription>Shipment metadata and current order outcome.</DialogDescription>
        </DialogHeader>
        <ApiErrorAlert
          v-if="!isDetailDialogLoading && detailShipmentQuery.error.value"
          message="Unable to load shipment."
        />
        <div
          v-else-if="isDetailDialogLoading"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          Loading shipment...
        </div>
        <template v-else-if="detailShipmentForDialog">
          <div class="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>
                  {{
                    detailShipmentForDialog.order?.reference ??
                    `Shipment #${detailShipmentForDialog.id}`
                  }}
                </CardTitle>
                <CardDescription>Courier and delivery metadata.</CardDescription>
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <p>
                  <span class="font-medium">Courier:</span> {{ detailShipmentForDialog.courier }}
                </p>
                <p>
                  <span class="font-medium">Tracking number:</span>
                  {{ detailShipmentForDialog.tracking_number ?? '-' }}
                </p>
                <p>
                  <span class="font-medium">Shipped at:</span>
                  {{
                    detailShipmentForDialog.shipped_at
                      ? formatDateTime(detailShipmentForDialog.shipped_at)
                      : '-'
                  }}
                </p>
                <p>
                  <span class="font-medium">Delivered at:</span>
                  {{
                    detailShipmentForDialog.delivered_at
                      ? formatDateTime(detailShipmentForDialog.delivered_at)
                      : '-'
                  }}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Order Outcome</CardTitle>
                <CardDescription>Current linked order status.</CardDescription>
              </CardHeader>
              <CardContent class="space-y-3 text-sm">
                <div class="inline-flex items-center gap-2">
                  <span class="font-medium">Status:</span>
                  <StatusBadge
                    :status="statusForShipment(detailShipmentForDialog.order?.current_status)"
                  />
                </div>
                <p>
                  <span class="font-medium">Order ID:</span>
                  {{ detailShipmentForDialog.order_id }}
                </p>
                <p>
                  <span class="font-medium">Created:</span>
                  {{ formatDateTime(detailShipmentForDialog.created_at) }}
                </p>
                <p>
                  <span class="font-medium">Updated:</span>
                  {{ formatDateTime(detailShipmentForDialog.updated_at) }}
                </p>
              </CardContent>
            </Card>
          </div>
        </template>
      </DialogContent>
    </Dialog>
  </section>
</template>
