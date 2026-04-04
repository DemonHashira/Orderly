<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowUpRight, Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useInventoryStocksQuery } from '@/features/inventory/composables/useInventoryQueries'
import {
  INVENTORY_STOCK_CONDITION_OPTIONS,
  type InventoryStockCondition,
} from '@/features/inventory/types'
import InventoryStocksDataTable from '@/features/inventory/ui/InventoryStocksDataTable.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
} from '@/shared/ui'
import { INVENTORY_STOCKS_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const STOCK_STATUS_OPTIONS = ['all', 'active', 'archived'] as const
const STOCK_CONDITION_FILTER_OPTIONS = ['all', ...INVENTORY_STOCK_CONDITION_OPTIONS] as const

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const { permissions } = useAuth()
const listModule = 'inventory_stocks' as const
const isSyncingFromRoute = ref(false)
const canCreateMovement = computed(() => permissions.value.includes('inventory.movement.create'))

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
const statusFilter = computed<(typeof STOCK_STATUS_OPTIONS)[number]>({
  get: () => {
    const value = listUiStore.modules[listModule].status
    if (value === 'active' || value === 'archived') {
      return value
    }
    return 'all'
  },
  set: (value) => listUiStore.setState(listModule, { status: value === 'all' ? '' : value }),
})
const stockConditionFilter = computed<(typeof STOCK_CONDITION_FILTER_OPTIONS)[number]>({
  get: () => {
    const value = listUiStore.modules[listModule].stock_condition
    if (INVENTORY_STOCK_CONDITION_OPTIONS.includes(value as InventoryStockCondition)) {
      return value as InventoryStockCondition
    }

    return 'all'
  },
  set: (value) =>
    listUiStore.setState(listModule, { stock_condition: value === 'all' ? '' : value }),
})
const debouncedSearch = useDebouncedRef(searchInput)

const stocksQuery = useInventoryStocksQuery(
  computed(() => ({
    page: page.value,
    per_page: perPage.value,
    q: debouncedSearch.value || undefined,
    is_active: statusFilter.value === 'all' ? undefined : statusFilter.value === 'active',
    stock_condition: stockConditionFilter.value === 'all' ? undefined : stockConditionFilter.value,
  })),
  {
    keepPreviousData: true,
  },
)

const stocks = computed(() => stocksQuery.data.value?.data ?? [])
const meta = computed(() => stocksQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(stocksQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && stocksQuery.isFetching.value)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>

    if (!listUiStore.hasRelevantQuery(normalizedQuery, INVENTORY_STOCKS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, INVENTORY_STOCKS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, INVENTORY_STOCKS_LIST_FIELDS, {
      status: (value: string) =>
        STOCK_STATUS_OPTIONS.includes(value as (typeof STOCK_STATUS_OPTIONS)[number]),
      stock_condition: (value: string) =>
        STOCK_CONDITION_FILTER_OPTIONS.includes(
          value as (typeof STOCK_CONDITION_FILTER_OPTIONS)[number],
        ),
    })

    void nextTick().then(() => {
      isSyncingFromRoute.value = false
    })
  },
  { immediate: true },
)

watch([debouncedSearch, statusFilter, stockConditionFilter], () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch(perPage, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch([debouncedSearch, statusFilter, stockConditionFilter, page, perPage], () => {
  if (isSyncingFromRoute.value) {
    return
  }

  const nextQuery = {
    ...listUiStore.toQuery(listModule, INVENTORY_STOCKS_LIST_FIELDS),
    ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
  }
  const normalizedCurrentQuery = listUiStore.normalizeQuery(
    listModule,
    route.query as Record<string, unknown>,
    INVENTORY_STOCKS_LIST_FIELDS,
    {
      status: (value: string) =>
        STOCK_STATUS_OPTIONS.includes(value as (typeof STOCK_STATUS_OPTIONS)[number]),
      stock_condition: (value: string) =>
        STOCK_CONDITION_FILTER_OPTIONS.includes(
          value as (typeof STOCK_CONDITION_FILTER_OPTIONS)[number],
        ),
    },
  )

  if (JSON.stringify(nextQuery) === JSON.stringify(normalizedCurrentQuery)) {
    return
  }

  void router.replace({ query: nextQuery })
})

const resetFilters = () => {
  searchInput.value = ''
  statusFilter.value = 'all'
  stockConditionFilter.value = 'all'
  perPage.value = 15
  page.value = 1
}

const openMovementHistory = async (productId: number) => {
  await router.push({
    path: '/inventory/movements',
    query: {
      product_id: String(productId),
    },
  })
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Inventory Stocks"
      description="Track on-hand, reserved, and available stock with quick movement drill-downs."
    >
      <template #actions>
        <Button v-if="canCreateMovement" as-child variant="outline" size="sm">
          <RouterLink to="/inventory/movements" data-test="inventory-stocks-open-movements">
            <ArrowUpRight data-icon="inline-start" />
            Record Movement
          </RouterLink>
        </Button>
      </template>
    </PageHeader>

    <Card class="gap-0">
      <CardHeader class="pb-4">
        <CardTitle class="text-base">Search & Filters</CardTitle>
        <CardDescription
          >Find stock by product or SKU, then narrow by stock condition and product
          status.</CardDescription
        >
      </CardHeader>
      <CardContent class="flex flex-col gap-3">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto_auto] xl:items-end">
          <div class="relative w-full xl:min-w-[360px]">
            <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
            <Input
              v-model="searchInput"
              class="pl-9"
              placeholder="Search by product or SKU…"
              name="inventory_stock_search"
              autocomplete="off"
              spellcheck="false"
              aria-label="Search inventory stocks"
              data-test="inventory-stocks-search"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 xl:justify-end">
            <Select v-model="stockConditionFilter">
              <SelectTrigger class="w-[200px]" data-test="inventory-stocks-condition-filter">
                <SelectValue placeholder="Stock condition" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All stock</SelectItem>
                <SelectItem value="low_stock">Low stock</SelectItem>
                <SelectItem value="out_of_stock">Out of stock</SelectItem>
                <SelectItem value="reserved">Reserved stock</SelectItem>
                <SelectItem value="available">Available stock</SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="statusFilter">
              <SelectTrigger class="w-[200px]" data-test="inventory-stocks-status-filter">
                <SelectValue placeholder="Product status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All products</SelectItem>
                <SelectItem value="active">Active only</SelectItem>
                <SelectItem value="archived">Archived only</SelectItem>
              </SelectContent>
            </Select>

            <Button variant="outline" class="min-w-23" @click="resetFilters">Reset</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="stocksQuery.error.value" message="Failed to load inventory stocks." />

    <EmptyStateCard
      v-if="!stocksQuery.isLoading.value && stocks.length === 0"
      title="No stock records"
      description="No inventory stocks match the current filters."
    />

    <Card v-else class="pb-3">
      <CardContent>
        <InventoryStocksDataTable
          v-if="meta"
          :rows="stocks"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total-rows="meta.total"
          :per-page="meta.per_page"
          @open-movements="openMovementHistory"
          @update:page="(nextPage) => (page = nextPage)"
          @update:per-page="(nextPerPage) => (perPage = nextPerPage)"
        />
      </CardContent>
    </Card>
  </section>
</template>
