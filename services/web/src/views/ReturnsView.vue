<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowUpRight, Plus, Search } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
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
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useCustomerQuery } from '@/features/customers/composables/useCustomersQueries'
import {
  useAddReturnItemMutation,
  useReturnQuery,
  useReturnsQuery,
  useRestockReturnMutation,
} from '@/features/returns/composables/useReturnsQueries'
import ReturnsDataTable from '@/features/returns/ui/ReturnsDataTable.vue'
import { formatDateTime } from '@/lib/formatters'
import { normalizeApiError } from '@/shared/api/errors'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  DateRangeFilter,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'
import { RETURNS_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'
import type { ReturnOrder } from '@/types'

const RESTOCKABLE_FILTER_OPTIONS = ['all', 'restockable', 'non_restockable'] as const

const asSingleQueryValue = (value: unknown): string | undefined => {
  if (typeof value === 'string') {
    return value
  }

  if (Array.isArray(value)) {
    return typeof value[0] === 'string' ? value[0] : undefined
  }

  return undefined
}

const normalizeReturnsRouteQuery = (query: Record<string, unknown>) => {
  const normalizedQuery = { ...query }
  const status = asSingleQueryValue(query.status)
  const hasRestockable = asSingleQueryValue(query.has_restockable)

  if (!status) {
    if (hasRestockable === 'true' || hasRestockable === '1') {
      normalizedQuery.status = 'restockable'
    }

    if (hasRestockable === 'false' || hasRestockable === '0') {
      normalizedQuery.status = 'non_restockable'
    }
  }

  return normalizedQuery
}

const { permissions } = useAuth()
const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'returns' as const
const isSyncingFromRoute = ref(false)

const mutationError = ref('')
const addItemFieldErrors = ref<Record<string, string>>({})
const addItemSubmitError = ref('')
const pendingRestockReturnId = ref<number | null>(null)
const persistedDetailReturn = ref<ReturnOrder | null>(null)

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

const restockableFilter = computed<(typeof RESTOCKABLE_FILTER_OPTIONS)[number]>({
  get: () => {
    const value = listUiStore.modules[listModule].status
    if (value === 'restockable' || value === 'non_restockable') {
      return value
    }
    return 'all'
  },
  set: (value) => listUiStore.setState(listModule, { status: value === 'all' ? '' : value }),
})

const returnedFrom = computed({
  get: () => listUiStore.modules[listModule].created_from,
  set: (value: string) => listUiStore.setState(listModule, { created_from: value }),
})
const returnedTo = computed({
  get: () => listUiStore.modules[listModule].created_to,
  set: (value: string) => listUiStore.setState(listModule, { created_to: value }),
})

const canRestock = computed(() => permissions.value.includes('returns.restock'))
const canAddItem = computed(() => permissions.value.includes('returns.item.add'))
const canViewCustomers = computed(() => permissions.value.includes('customers.view'))

const returnsQuery = useReturnsQuery(
  computed(() => ({
    page: page.value,
    per_page: perPage.value,
    reason: debouncedSearch.value || undefined,
    returned_from: returnedFrom.value || undefined,
    returned_to: returnedTo.value || undefined,
    has_restockable:
      restockableFilter.value === 'all' ? undefined : restockableFilter.value === 'restockable',
  })),
  {
    keepPreviousData: true,
  },
)

const restockMutation = useRestockReturnMutation()
const addItemMutation = useAddReturnItemMutation()

const returnOrders = computed(() => returnsQuery.data.value?.data ?? [])
const meta = computed(() => returnsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(returnsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && returnsQuery.isFetching.value)

const isDetailRoute = computed(() => route.name === 'return-detail')
const detailReturnId = computed(() => Number(route.params.id))
const detailReturnQuery = useReturnQuery(detailReturnId)
const detailReturn = computed(() => detailReturnQuery.data.value?.data ?? null)
const detailReturnForDialog = computed(() => detailReturn.value ?? persistedDetailReturn.value)
const isDetailDialogLoading = computed(
  () => isDetailRoute.value && detailReturnQuery.isLoading.value,
)

const detailCustomerId = computed(() => {
  if (!canViewCustomers.value) {
    return 0
  }

  return detailReturnForDialog.value?.order?.customer_id ?? 0
})
const detailCustomerQuery = useCustomerQuery(detailCustomerId)
const detailCustomer = computed(() => detailCustomerQuery.data.value?.data ?? null)
const detailCustomerLabel = computed(() => {
  if (detailCustomerQuery.isLoading.value && detailCustomerId.value > 0) {
    return 'Loading customer…'
  }

  if (detailCustomer.value?.name) {
    return detailCustomer.value.name
  }

  return detailCustomerId.value > 0 ? `#${detailCustomerId.value}` : '-'
})
const detailCustomerContact = computed(() => {
  if (!detailCustomer.value) {
    return ''
  }

  return [detailCustomer.value.email, detailCustomer.value.phone].filter(Boolean).join(' · ')
})

const addItemForm = ref({
  product_id: '',
  quantity: '1',
  restockable: true,
})

const addItemRestockableModel = computed({
  get: () => (addItemForm.value.restockable ? 'restockable' : 'non_restockable'),
  set: (value: string) => {
    addItemForm.value.restockable = value === 'restockable'
  },
})

const returnedQtyByProductId = computed(() => {
  const entries = new Map<number, number>()

  for (const item of detailReturnForDialog.value?.items ?? []) {
    entries.set(item.product_id, (entries.get(item.product_id) ?? 0) + item.quantity)
  }

  return entries
})

const productNameById = computed(() => {
  const entries = (detailReturnForDialog.value?.order?.items ?? [])
    .filter((item) => item.product != null)
    .map((item) => [item.product_id, `${item.product?.name} (${item.product?.sku})`] as const)

  return new Map<number, string>(entries)
})

const orderItems = computed(() => detailReturnForDialog.value?.order?.items ?? [])

const availableProducts = computed(() => {
  return orderItems.value
    .map((item) => {
      const alreadyReturnedQty = returnedQtyByProductId.value.get(item.product_id) ?? 0
      const remainingQty = Math.max(0, item.quantity - alreadyReturnedQty)

      return {
        id: item.product_id,
        orderedQty: item.quantity,
        alreadyReturnedQty,
        remainingQty,
        label: productNameById.value.get(item.product_id) ?? `Product #${item.product_id}`,
      }
    })
    .filter((item) => item.remainingQty > 0)
    .sort((a, b) => a.label.localeCompare(b.label))
})

const selectedProductAvailability = computed(() => {
  const productId = Number(addItemForm.value.product_id)
  if (!Number.isInteger(productId) || productId <= 0) {
    return null
  }

  return availableProducts.value.find((product) => product.id === productId) ?? null
})

const isAddItemContextLoading = computed(() => canAddItem.value && isDetailDialogLoading.value)

const detailRestockableItemCount = computed(() =>
  detailReturnForDialog.value?.restocked_at
    ? 0
    : (detailReturnForDialog.value?.items?.filter((item) => item.restockable).length ?? 0),
)

const canRestockDetailReturn = computed(
  () => canRestock.value && detailRestockableItemCount.value > 0,
)

const confirmDialogOpen = computed({
  get: () => pendingRestockReturnId.value != null,
  set: (value: boolean) => {
    if (!value) {
      pendingRestockReturnId.value = null
    }
  },
})

const isRestockMutationPending = computed(() => restockMutation.isPending.value)

const isReturnDetailDialogOpen = computed({
  get: () => isDetailRoute.value,
  set: (value: boolean) => {
    if (!value) {
      void closeReturnDialog()
    }
  },
})

watch(
  detailReturn,
  (returnOrder) => {
    if (returnOrder) {
      persistedDetailReturn.value = returnOrder
    }
  },
  { immediate: true },
)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = normalizeReturnsRouteQuery(query as Record<string, unknown>)

    if (!listUiStore.hasRelevantQuery(normalizedQuery, RETURNS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, RETURNS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, RETURNS_LIST_FIELDS, {
      status: (value: string) =>
        RESTOCKABLE_FILTER_OPTIONS.includes(value as (typeof RESTOCKABLE_FILTER_OPTIONS)[number]),
    })

    void nextTick().then(() => {
      isSyncingFromRoute.value = false
    })
  },
  { immediate: true },
)

watch([debouncedSearch, restockableFilter, returnedFrom, returnedTo], () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch(perPage, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch([debouncedSearch, restockableFilter, returnedFrom, returnedTo, page, perPage], () => {
  if (isSyncingFromRoute.value) {
    return
  }

  const nextQuery = {
    ...listUiStore.toQuery(listModule, RETURNS_LIST_FIELDS),
    ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
  }
  const normalizedCurrentQuery = listUiStore.normalizeQuery(
    listModule,
    normalizeReturnsRouteQuery(route.query as Record<string, unknown>),
    RETURNS_LIST_FIELDS,
    {
      status: (value: string) =>
        RESTOCKABLE_FILTER_OPTIONS.includes(value as (typeof RESTOCKABLE_FILTER_OPTIONS)[number]),
    },
  )

  if (JSON.stringify(nextQuery) === JSON.stringify(normalizedCurrentQuery)) {
    return
  }

  void router.replace({ query: nextQuery })
})

watch(
  availableProducts,
  (products) => {
    if (products.length === 0) {
      addItemForm.value.product_id = ''
      return
    }

    if (products.some((product) => String(product.id) === addItemForm.value.product_id)) {
      return
    }

    const [firstProduct] = products
    if (!firstProduct) {
      addItemForm.value.product_id = ''
      return
    }

    addItemForm.value.product_id = String(firstProduct.id)
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

const openRestockDialog = (returnId: number) => {
  mutationError.value = ''
  pendingRestockReturnId.value = returnId
}

const onConfirmRestock = async () => {
  if (pendingRestockReturnId.value == null) {
    return
  }

  mutationError.value = ''

  try {
    await restockMutation.mutateAsync(pendingRestockReturnId.value)
    toast.success('Return restocked successfully.')
    pendingRestockReturnId.value = null
  } catch (error: unknown) {
    mutationError.value = normalizeApiError(error).message
  }
}

const onAddItemSubmit = async () => {
  const returnOrder = detailReturnForDialog.value
  if (!returnOrder) {
    return
  }

  addItemSubmitError.value = ''
  addItemFieldErrors.value = {}

  const productId = Number(addItemForm.value.product_id)
  const quantity = Number(addItemForm.value.quantity)

  if (!Number.isInteger(productId) || productId <= 0) {
    addItemFieldErrors.value = {
      product_id: 'Select a valid product.',
    }
    return
  }

  if (!Number.isInteger(quantity) || quantity <= 0) {
    addItemFieldErrors.value = {
      quantity: 'Quantity must be a positive whole number.',
    }
    return
  }

  const availability = availableProducts.value.find((product) => product.id === productId)
  if (!availability) {
    addItemFieldErrors.value = {
      product_id: 'Selected product is not available for returns.',
    }
    return
  }

  if (quantity > availability.remainingQty) {
    addItemFieldErrors.value = {
      quantity: `Quantity exceeds remaining returnable units (${availability.remainingQty}).`,
    }
    return
  }

  try {
    await addItemMutation.mutateAsync({
      id: returnOrder.id,
      payload: {
        product_id: productId,
        quantity,
        restockable: addItemForm.value.restockable,
      },
    })

    addItemForm.value.quantity = '1'
    toast.success('Return item added successfully.')
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    addItemFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    addItemSubmitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const closeReturnDialog = async () => {
  await router.push({
    path: '/returns',
    query: route.query,
  })
}

const applyPreset = (preset: 'all' | 'last_7' | 'last_30') => {
  if (preset === 'all') {
    returnedFrom.value = ''
    returnedTo.value = ''
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

  returnedFrom.value = formatDate(start)
  returnedTo.value = formatDate(end)
}

const resetFilters = () => {
  searchInput.value = ''
  restockableFilter.value = 'all'
  returnedFrom.value = ''
  returnedTo.value = ''
  perPage.value = 15
  page.value = 1
}

const openRestockQueue = () => {
  restockableFilter.value = 'restockable'
}

const restockableText = (value: boolean) => (value ? 'Yes' : 'No')
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Returns" description="Manage return records and restock decisions.">
      <template #actions>
        <Button
          v-if="permissions.includes('returns.view')"
          variant="outline"
          size="sm"
          data-test="returns-open-restock-queue"
          @click="openRestockQueue"
        >
          <ArrowUpRight data-icon="inline-start" />
          Restock Queue
        </Button>
      </template>
    </PageHeader>

    <Card class="gap-0">
      <CardHeader class="pb-4">
        <CardTitle class="text-base">Search & Filters</CardTitle>
        <CardDescription>
          Filter by reason, restockability, and returned date range.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
          <div class="relative w-full xl:min-w-[360px]">
            <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
            <Input
              v-model="searchInput"
              class="pl-9"
              placeholder="Search by return reason…"
              name="returns_reason_search"
              autocomplete="off"
              spellcheck="false"
              aria-label="Search returns by reason"
              data-test="returns-search"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 xl:justify-end">
            <Select v-model="restockableFilter">
              <SelectTrigger class="w-[220px] shrink-0" data-test="returns-restockable-filter">
                <SelectValue placeholder="Restockable filter" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All returns</SelectItem>
                <SelectItem value="restockable">Has restockable items</SelectItem>
                <SelectItem value="non_restockable">No restockable items</SelectItem>
              </SelectContent>
            </Select>

            <DateRangeFilter
              class="shrink-0"
              :from="returnedFrom"
              :to="returnedTo"
              @update:from="(value) => (returnedFrom = value)"
              @update:to="(value) => (returnedTo = value)"
              @preset="applyPreset"
            />

            <Button variant="outline" class="min-w-23 shrink-0" @click="resetFilters">Reset</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="returnsQuery.error.value" message="Failed to load returns." />
    <ApiErrorAlert v-if="mutationError" :message="mutationError" />

    <EmptyStateCard
      v-if="!returnsQuery.isLoading.value && returnOrders.length === 0"
      title="No returns"
      description="No return records match the current filters."
    />

    <Card v-else class="pb-3">
      <CardContent>
        <ReturnsDataTable
          v-if="meta"
          :rows="returnOrders"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total-rows="meta.total"
          :per-page="meta.per_page"
          :can-restock="canRestock"
          @restock="(id) => openRestockDialog(id)"
          @update:page="(nextPage) => (page = nextPage)"
          @update:per-page="(nextPerPage) => (perPage = nextPerPage)"
        />
      </CardContent>
    </Card>

    <AlertDialog v-model:open="confirmDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Restock return</AlertDialogTitle>
          <AlertDialogDescription>
            Apply restockable quantities from this return back to inventory.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel :disabled="isRestockMutationPending">Cancel</AlertDialogCancel>
          <Button
            :disabled="isRestockMutationPending"
            data-test="returns-confirm-action"
            @click="onConfirmRestock"
          >
            Restock
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <Dialog :open="isReturnDetailDialogOpen" @update:open="(open) => !open && closeReturnDialog()">
      <DialogContent
        class="max-h-dvh w-[calc(100vw-1.5rem)] overflow-y-auto p-4 sm:max-w-5xl sm:p-6"
        data-test="return-detail-dialog"
      >
        <DialogHeader>
          <DialogTitle>Return Detail</DialogTitle>
          <DialogDescription>Review returned items and manage restock decisions.</DialogDescription>
        </DialogHeader>

        <ApiErrorAlert
          v-if="!isDetailDialogLoading && detailReturnQuery.error.value"
          message="Unable to load return detail."
        />

        <div
          v-else-if="isDetailDialogLoading"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          Loading return…
        </div>

        <template v-else-if="detailReturnForDialog">
          <div class="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>
                  {{
                    detailReturnForDialog.order?.reference ?? `Return #${detailReturnForDialog.id}`
                  }}
                </CardTitle>
                <CardDescription>Return metadata and customer-reported reason.</CardDescription>
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <p>
                  <span class="font-medium">Reason:</span>
                  {{ detailReturnForDialog.reason ?? '-' }}
                </p>
                <p>
                  <span class="font-medium">Customer:</span>
                  {{ detailCustomerLabel }}
                </p>
                <p v-if="detailCustomerContact">
                  <span class="font-medium">Contact:</span>
                  {{ detailCustomerContact }}
                </p>
                <p>
                  <span class="font-medium">Returned at:</span>
                  {{
                    detailReturnForDialog.returned_at
                      ? formatDateTime(detailReturnForDialog.returned_at)
                      : '-'
                  }}
                </p>
                <p v-if="detailReturnForDialog.restocked_at">
                  <span class="font-medium">Restocked at:</span>
                  {{ formatDateTime(detailReturnForDialog.restocked_at) }}
                </p>
                <p>
                  <span class="font-medium">Created:</span>
                  {{ formatDateTime(detailReturnForDialog.created_at) }}
                </p>
                <p>
                  <span class="font-medium">Updated:</span>
                  {{ formatDateTime(detailReturnForDialog.updated_at) }}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Linked Order</CardTitle>
                <CardDescription>Current order lifecycle state.</CardDescription>
              </CardHeader>
              <CardContent class="space-y-3 text-sm">
                <div class="inline-flex items-center gap-2">
                  <span class="font-medium">Status:</span>
                  <StatusBadge
                    :status="detailReturnForDialog.order?.current_status ?? 'returned'"
                  />
                </div>
                <p>
                  <span class="font-medium">Order ID:</span>
                  {{ detailReturnForDialog.order_id }}
                </p>
                <p>
                  <span class="font-medium">Restockable items:</span>
                  {{ detailRestockableItemCount }}
                </p>
                <Button
                  v-if="canRestockDetailReturn"
                  size="sm"
                  variant="outline"
                  data-test="return-detail-restock"
                  @click="openRestockDialog(detailReturnForDialog.id)"
                >
                  Restock Return
                </Button>
              </CardContent>
            </Card>
          </div>

          <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,320px)]">
            <Card class="min-w-0">
              <CardHeader>
                <CardTitle>Return Items</CardTitle>
                <CardDescription
                  >Quantities and restockability for each returned product.</CardDescription
                >
              </CardHeader>
              <CardContent>
                <div class="rounded-md border">
                  <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                      <tr>
                        <th class="p-2 text-left font-medium">Product</th>
                        <th class="p-2 text-left font-medium">SKU</th>
                        <th class="p-2 text-right font-medium">Qty</th>
                        <th class="p-2 text-right font-medium">Restockable</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="(detailReturnForDialog.items ?? []).length === 0" class="border-t">
                        <td colspan="4" class="text-muted-foreground p-4 text-center">
                          No return items recorded.
                        </td>
                      </tr>
                      <tr
                        v-for="item in detailReturnForDialog.items ?? []"
                        :key="item.id"
                        class="border-t"
                      >
                        <td class="p-2">{{ item.product?.name ?? `#${item.product_id}` }}</td>
                        <td class="p-2">{{ item.product?.sku ?? '-' }}</td>
                        <td class="p-2 text-right tabular-nums">{{ item.quantity }}</td>
                        <td class="p-2 text-right">{{ restockableText(item.restockable) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Add Return Item</CardTitle>
                <CardDescription
                  >Add additional returned quantities to this return record.</CardDescription
                >
              </CardHeader>
              <CardContent class="min-w-0 space-y-3">
                <ApiErrorAlert v-if="addItemSubmitError" :message="addItemSubmitError" />

                <div
                  v-if="!canAddItem"
                  class="text-muted-foreground rounded-md border border-dashed p-3 text-sm"
                >
                  You do not have permission to add return items.
                </div>

                <div
                  v-else-if="isAddItemContextLoading"
                  class="text-muted-foreground rounded-md border border-dashed p-3 text-sm"
                >
                  Loading available products…
                </div>

                <div
                  v-else-if="availableProducts.length === 0"
                  class="text-muted-foreground rounded-md border border-dashed p-3 text-sm"
                >
                  All order quantities have already been returned.
                </div>

                <form
                  v-else
                  class="min-w-0 space-y-4"
                  data-test="returns-add-item-form"
                  @submit.prevent="onAddItemSubmit"
                >
                  <div class="space-y-1">
                    <label class="text-sm font-medium" for="return-item-product">Product</label>
                    <Select v-model="addItemForm.product_id">
                      <SelectTrigger
                        id="return-item-product"
                        class="w-full min-w-0 max-w-full"
                        data-test="returns-add-item-product"
                      >
                        <SelectValue placeholder="Select product" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="product in availableProducts"
                          :key="product.id"
                          :value="String(product.id)"
                        >
                          {{ product.label }} · {{ product.remainingQty }} remaining
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="addItemFieldErrors.product_id" class="text-destructive text-xs">
                      {{ addItemFieldErrors.product_id }}
                    </p>
                  </div>

                  <div class="space-y-1">
                    <label class="text-sm font-medium" for="return-item-quantity">Quantity</label>
                    <Input
                      id="return-item-quantity"
                      v-model="addItemForm.quantity"
                      min="1"
                      step="1"
                      type="number"
                      name="return_item_quantity"
                      autocomplete="off"
                      inputmode="numeric"
                      data-test="returns-add-item-quantity"
                    />
                    <p v-if="selectedProductAvailability" class="text-muted-foreground text-xs">
                      Max addable quantity: {{ selectedProductAvailability.remainingQty }}
                    </p>
                    <p v-if="addItemFieldErrors.quantity" class="text-destructive text-xs">
                      {{ addItemFieldErrors.quantity }}
                    </p>
                  </div>

                  <div class="space-y-2">
                    <label class="text-sm font-medium" for="return-item-restockable">
                      Restockability
                    </label>
                    <Select v-model="addItemRestockableModel">
                      <SelectTrigger
                        id="return-item-restockable"
                        data-test="returns-add-item-restockable"
                      >
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="restockable">Restockable</SelectItem>
                        <SelectItem value="non_restockable">Non-restockable</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <label class="flex items-center gap-2 text-sm font-medium">
                    <Checkbox :model-value="addItemForm.restockable" disabled />
                    {{
                      addItemForm.restockable
                        ? 'Will restock inventory'
                        : 'Will not restock inventory'
                    }}
                  </label>

                  <Button
                    class="w-full"
                    type="submit"
                    :disabled="addItemMutation.isPending.value"
                    data-test="returns-add-item-submit"
                  >
                    <Plus data-icon="inline-start" />
                    Add Item
                  </Button>
                </form>
              </CardContent>
            </Card>
          </div>
        </template>
      </DialogContent>
    </Dialog>
  </section>
</template>
