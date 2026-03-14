<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertCircle, Plus, Search } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAuth } from '@/features/auth/composables/useAuth'
import {
  useCreateInventoryMovementMutation,
  useInventoryMovementsQuery,
} from '@/features/inventory/composables/useInventoryQueries'
import type { InventoryProductOption } from '@/features/inventory/types'
import {
  INVENTORY_MOVEMENT_TYPE_OPTIONS,
  type InventoryMovementType,
} from '@/features/inventory/types'
import InventoryMovementsDataTable from '@/features/inventory/ui/InventoryMovementsDataTable.vue'
import InventoryProductCombobox from '@/features/inventory/ui/InventoryProductCombobox.vue'
import { useProductsQuery } from '@/features/products/composables/useProductsQueries'
import { isPositiveIntegerString } from '@/lib/utils'
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
} from '@/shared/ui'
import { INVENTORY_MOVEMENTS_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const { permissions } = useAuth()
const listModule = 'inventory_movements' as const
const isSyncingFromRoute = ref(false)
const MOVEMENT_TYPE_FILTER_OPTIONS = ['all', ...INVENTORY_MOVEMENT_TYPE_OPTIONS] as const

const movementSuccessMessage = ref('')
const createDialogOpen = ref(false)
const createFieldErrors = ref<Record<string, string>>({})
const createSubmitError = ref('')
const filterProductSearch = ref('')
const createProductSearch = ref('')

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
const movementTypeFilter = computed<(typeof MOVEMENT_TYPE_FILTER_OPTIONS)[number]>({
  get: () => {
    const value = listUiStore.modules[listModule].type
    if (INVENTORY_MOVEMENT_TYPE_OPTIONS.includes(value as InventoryMovementType)) {
      return value as InventoryMovementType
    }

    return 'all'
  },
  set: (value) => listUiStore.setState(listModule, { type: value === 'all' ? '' : value }),
})
const filteredProductId = computed<string>({
  get: () => listUiStore.modules[listModule].product_id,
  set: (value: string) => listUiStore.setState(listModule, { product_id: value }),
})
const createdFrom = computed<string>({
  get: () => listUiStore.modules[listModule].created_from,
  set: (value: string) => listUiStore.setState(listModule, { created_from: value }),
})
const createdTo = computed<string>({
  get: () => listUiStore.modules[listModule].created_to,
  set: (value: string) => listUiStore.setState(listModule, { created_to: value }),
})

const debouncedSearch = useDebouncedRef(searchInput)
const debouncedFilterProductSearch = useDebouncedRef(filterProductSearch)
const debouncedCreateProductSearch = useDebouncedRef(createProductSearch)

const canCreateMovement = computed(() => permissions.value.includes('inventory.movement.create'))
const canViewProducts = computed(() => permissions.value.includes('products.view'))

const movementsQuery = useInventoryMovementsQuery(
  computed(() => ({
    page: page.value,
    per_page: perPage.value,
    q: debouncedSearch.value || undefined,
    type: movementTypeFilter.value === 'all' ? undefined : movementTypeFilter.value,
    product_id: filteredProductId.value ? Number(filteredProductId.value) : undefined,
    from: createdFrom.value || undefined,
    to: createdTo.value || undefined,
  })),
  {
    keepPreviousData: true,
  },
)

const filterProductsQuery = useProductsQuery(
  computed(() => ({
    page: 1,
    per_page: 100,
    q: debouncedFilterProductSearch.value || undefined,
    is_active: true,
  })),
  {
    enabled: computed(
      () =>
        canViewProducts.value &&
        (filteredProductId.value.length > 0 ||
          debouncedFilterProductSearch.value.trim().length > 0),
    ),
  },
)

const createProductsQuery = useProductsQuery(
  computed(() => ({
    page: 1,
    per_page: 100,
    q: debouncedCreateProductSearch.value || undefined,
    is_active: true,
  })),
  {
    enabled: computed(() => canViewProducts.value && createDialogOpen.value),
  },
)

const createMovementMutation = useCreateInventoryMovementMutation()

const movements = computed(() => movementsQuery.data.value?.data ?? [])
const meta = computed(() => movementsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(movementsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && movementsQuery.isFetching.value)

const createForm = ref({
  product_id: '',
  type: 'adjustment' as InventoryMovementType,
  quantity_delta: '',
  reason: '',
})

const mapProductOptions = (rows?: Array<{ id: number; name: string; sku: string }>) =>
  (rows ?? []).map(
    (product) =>
      ({
        id: product.id,
        sku: product.sku,
        label: `${product.name} (${product.sku})`,
      }) satisfies InventoryProductOption,
  )

const filterProductOptions = computed(() => mapProductOptions(filterProductsQuery.data.value?.data))
const createProductOptions = computed(() => mapProductOptions(createProductsQuery.data.value?.data))

const selectedFilterProductLabel = computed(
  () =>
    filterProductOptions.value.find((product) => String(product.id) === filteredProductId.value)
      ?.label ?? '',
)
const selectedCreateProductLabel = computed(
  () =>
    createProductOptions.value.find((product) => String(product.id) === createForm.value.product_id)
      ?.label ?? '',
)

watch(
  filterProductOptions,
  (options) => {
    if (!filteredProductId.value || filterProductSearch.value.trim().length > 0) {
      return
    }

    const match = options.find((product) => String(product.id) === filteredProductId.value)
    if (match) {
      filterProductSearch.value = match.label
    }
  },
  { immediate: true },
)

watch(filteredProductId, (value) => {
  if (!value) {
    filterProductSearch.value = ''
  }
})

watch(createDialogOpen, (open) => {
  if (!open) {
    createFieldErrors.value = {}
    createSubmitError.value = ''
    createProductSearch.value = ''
    createForm.value = {
      product_id: '',
      type: 'adjustment',
      quantity_delta: '',
      reason: '',
    }
  }
})

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>

    if (!listUiStore.hasRelevantQuery(normalizedQuery, INVENTORY_MOVEMENTS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, INVENTORY_MOVEMENTS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, INVENTORY_MOVEMENTS_LIST_FIELDS, {
      type: (value: string) =>
        MOVEMENT_TYPE_FILTER_OPTIONS.includes(
          value as (typeof MOVEMENT_TYPE_FILTER_OPTIONS)[number],
        ),
      product_id: (value: string) => isPositiveIntegerString(value),
    })

    void nextTick().then(() => {
      isSyncingFromRoute.value = false
    })
  },
  { immediate: true },
)

watch([debouncedSearch, movementTypeFilter, filteredProductId, createdFrom, createdTo], () => {
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
  [debouncedSearch, movementTypeFilter, filteredProductId, createdFrom, createdTo, page, perPage],
  () => {
    if (isSyncingFromRoute.value) {
      return
    }

    const nextQuery = {
      ...listUiStore.toQuery(listModule, INVENTORY_MOVEMENTS_LIST_FIELDS),
      ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
    }
    const normalizedCurrentQuery = listUiStore.normalizeQuery(
      listModule,
      route.query as Record<string, unknown>,
      INVENTORY_MOVEMENTS_LIST_FIELDS,
      {
        type: (value: string) =>
          MOVEMENT_TYPE_FILTER_OPTIONS.includes(
            value as (typeof MOVEMENT_TYPE_FILTER_OPTIONS)[number],
          ),
        product_id: (value: string) => isPositiveIntegerString(value),
      },
    )

    if (JSON.stringify(nextQuery) === JSON.stringify(normalizedCurrentQuery)) {
      return
    }

    void router.replace({ query: nextQuery })
  },
)

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
  movementTypeFilter.value = 'all'
  filteredProductId.value = ''
  filterProductSearch.value = ''
  createdFrom.value = ''
  createdTo.value = ''
  perPage.value = 15
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

const submitMovement = async () => {
  movementSuccessMessage.value = ''
  createFieldErrors.value = {}
  createSubmitError.value = ''

  const productId = Number(createForm.value.product_id)
  const quantityDelta = Number(createForm.value.quantity_delta)
  const reason = createForm.value.reason.trim()

  if (!Number.isInteger(productId) || productId <= 0) {
    createFieldErrors.value = {
      product_id: 'Select a valid product.',
    }
    return
  }

  if (!Number.isInteger(quantityDelta) || quantityDelta === 0) {
    createFieldErrors.value = {
      quantity_delta: 'Quantity delta must be a non-zero whole number.',
    }
    return
  }

  if (createForm.value.type === 'restock' && quantityDelta < 1) {
    createFieldErrors.value = {
      quantity_delta: 'Restock movements must increase stock.',
    }
    return
  }

  if (createForm.value.type === 'damage' && quantityDelta > -1) {
    createFieldErrors.value = {
      quantity_delta: 'Damage movements must decrease stock.',
    }
    return
  }

  if (reason.length === 0) {
    createFieldErrors.value = {
      reason: 'Reason is required.',
    }
    return
  }

  try {
    await createMovementMutation.mutateAsync({
      product_id: productId,
      type: createForm.value.type,
      quantity_delta: quantityDelta,
      reason,
    })

    movementSuccessMessage.value = 'Manual inventory movement created successfully.'
    createDialogOpen.value = false
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    createFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    createSubmitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const quantityHint = computed(() => {
  if (createForm.value.type === 'restock') {
    return 'Use a positive whole number to add stock back in.'
  }

  if (createForm.value.type === 'damage') {
    return 'Use a negative whole number to remove damaged stock.'
  }

  return 'Use a positive or negative whole number for a manual adjustment.'
})
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Inventory Movements"
      description="Monitor stock history and record manual adjustments without leaving the workspace."
    >
      <template #actions>
        <Button
          v-if="canCreateMovement"
          data-test="inventory-open-create-dialog"
          @click="createDialogOpen = true"
        >
          <Plus class="mr-1 size-4" />
          Create Movement
        </Button>
      </template>
    </PageHeader>

    <Card class="gap-0">
      <CardHeader class="pb-4">
        <CardTitle class="text-base">Search & Filters</CardTitle>
        <CardDescription>
          Search by product or SKU, then narrow by product, movement type, and date range.
        </CardDescription>
      </CardHeader>
      <CardContent class="flex flex-col gap-3">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,280px)_auto] xl:items-end">
          <div class="relative w-full">
            <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
            <Input
              v-model="searchInput"
              class="pl-9"
              placeholder="Search by product or SKU…"
              name="inventory_movement_search"
              autocomplete="off"
              spellcheck="false"
              aria-label="Search inventory movements"
              data-test="inventory-movements-search"
            />
          </div>

          <InventoryProductCombobox
            v-model="filteredProductId"
            v-model:search-value="filterProductSearch"
            :options="filterProductOptions"
            :loading="filterProductsQuery.isFetching.value"
            :selected-label="selectedFilterProductLabel"
            placeholder="Filter by product"
            empty-message="Start typing to find an active product."
            data-test="inventory-movements-product-filter"
          />

          <div class="flex flex-wrap items-center gap-2 xl:justify-end">
            <Select v-model="movementTypeFilter">
              <SelectTrigger class="w-[180px]" data-test="inventory-movements-type-filter">
                <SelectValue placeholder="Movement type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All types</SelectItem>
                <SelectItem
                  v-for="type in INVENTORY_MOVEMENT_TYPE_OPTIONS"
                  :key="type"
                  :value="type"
                >
                  {{ type }}
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
      v-if="movementsQuery.error.value"
      message="Failed to load inventory movements."
    />

    <Alert v-if="movementSuccessMessage">
      <AlertCircle />
      <AlertTitle>Movement created</AlertTitle>
      <AlertDescription>{{ movementSuccessMessage }}</AlertDescription>
    </Alert>

    <EmptyStateCard
      v-if="!movementsQuery.isLoading.value && movements.length === 0"
      title="No movements"
      description="No inventory movements match the current filters."
    />

    <Card v-else class="pb-3">
      <CardContent>
        <InventoryMovementsDataTable
          v-if="meta"
          :rows="movements"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total-rows="meta.total"
          :per-page="meta.per_page"
          @update:page="(nextPage) => (page = nextPage)"
          @update:per-page="(nextPerPage) => (perPage = nextPerPage)"
        />
      </CardContent>
    </Card>

    <Dialog v-model:open="createDialogOpen">
      <DialogContent class="sm:max-w-2xl" data-test="inventory-create-dialog">
        <DialogHeader>
          <DialogTitle>Create Inventory Movement</DialogTitle>
          <DialogDescription>
            Record a manual adjustment, damage write-off, or restock entry.
          </DialogDescription>
        </DialogHeader>

        <ApiErrorAlert v-if="createSubmitError" :message="createSubmitError" />

        <form
          class="flex flex-col gap-4"
          data-test="inventory-create-form"
          @submit.prevent="submitMovement"
        >
          <FieldGroup class="gap-4">
            <Field>
              <FieldLabel for="inventory-create-product">Product</FieldLabel>
              <InventoryProductCombobox
                v-model="createForm.product_id"
                v-model:search-value="createProductSearch"
                :options="createProductOptions"
                :loading="createProductsQuery.isFetching.value"
                :selected-label="selectedCreateProductLabel"
                input-id="inventory-create-product"
                placeholder="Search active products"
                empty-message="No matching active products found."
                data-test="inventory-create-product"
              />
              <FieldError
                v-if="createFieldErrors.product_id"
                :errors="[createFieldErrors.product_id]"
              />
            </Field>

            <Field>
              <FieldLabel for="inventory-create-type">Movement type</FieldLabel>
              <Select v-model="createForm.type">
                <SelectTrigger id="inventory-create-type" data-test="inventory-create-type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="type in INVENTORY_MOVEMENT_TYPE_OPTIONS"
                    :key="type"
                    :value="type"
                  >
                    {{ type }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </Field>

            <Field>
              <FieldLabel for="inventory-create-quantity">Quantity delta</FieldLabel>
              <Input
                id="inventory-create-quantity"
                v-model="createForm.quantity_delta"
                type="number"
                step="1"
                name="inventory_quantity_delta"
                autocomplete="off"
                inputmode="numeric"
                data-test="inventory-create-quantity"
              />
              <p class="text-muted-foreground text-xs">{{ quantityHint }}</p>
              <FieldError
                v-if="createFieldErrors.quantity_delta"
                :errors="[createFieldErrors.quantity_delta]"
              />
            </Field>

            <Field>
              <FieldLabel for="inventory-create-reason">Reason</FieldLabel>
              <Input
                id="inventory-create-reason"
                v-model="createForm.reason"
                name="inventory_reason"
                autocomplete="off"
                data-test="inventory-create-reason"
              />
              <FieldError v-if="createFieldErrors.reason" :errors="[createFieldErrors.reason]" />
            </Field>
          </FieldGroup>

          <div class="flex items-center justify-end gap-2">
            <Button type="button" variant="outline" @click="createDialogOpen = false"
              >Cancel</Button
            >
            <Button
              type="submit"
              :disabled="createMovementMutation.isPending.value"
              data-test="inventory-create-submit"
            >
              <Plus v-if="!createMovementMutation.isPending.value" data-icon="inline-start" />
              {{ createMovementMutation.isPending.value ? 'Saving...' : 'Create movement' }}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </section>
</template>
