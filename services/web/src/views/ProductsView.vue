<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { formatCurrency } from '@/lib/formatters'
import {
  useArchiveProductMutation,
  useProductsQuery,
} from '@/features/products/composables/useProductsQueries'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  ConfirmActionDialog,
  DebouncedSearchInput,
  EmptyStateCard,
  PageInitialSkeleton,
  PageHeader,
  PageRefetchOverlay,
  ServerPagination,
  StatusBadge,
} from '@/shared/ui'
import { BASIC_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'products' as const
const isSyncingFromRoute = ref(false)

const page = computed({
  get: () => listUiStore.modules[listModule].page,
  set: (value: number) => listUiStore.setState(listModule, { page: value }),
})
const search = computed({
  get: () => listUiStore.modules[listModule].q,
  set: (value: string) => listUiStore.setState(listModule, { q: value }),
})
const debouncedSearch = useDebouncedRef(search)

const productsQuery = useProductsQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    q: debouncedSearch.value,
  })),
)

const archiveMutation = useArchiveProductMutation()

const products = computed(() => productsQuery.data.value?.data ?? [])
const meta = computed(() => productsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(productsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && productsQuery.isFetching.value)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>
    if (!listUiStore.hasRelevantQuery(normalizedQuery, BASIC_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, BASIC_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
      }
      return
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, BASIC_LIST_FIELDS)
    isSyncingFromRoute.value = false
  },
  { immediate: true },
)

watch(search, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch([debouncedSearch, page], () => {
  if (isSyncingFromRoute.value) {
    return
  }

  const nextQuery = {
    ...listUiStore.toQuery(listModule, BASIC_LIST_FIELDS),
    ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
  }
  const currentQuery = listUiStore.normalizeQuery(
    listModule,
    route.query as Record<string, unknown>,
    BASIC_LIST_FIELDS,
  )

  if (JSON.stringify(nextQuery) === JSON.stringify(currentQuery)) {
    return
  }

  void router.replace({
    query: nextQuery,
  })
})
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Products" description="Manage product catalog and archive inactive SKUs." />

    <Card>
      <CardContent class="pt-6">
        <DebouncedSearchInput v-model="search" placeholder="Search by SKU or name" />
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="productsQuery.error.value" message="Failed to load products." />

    <EmptyStateCard
      v-if="!productsQuery.isLoading.value && products.length === 0"
      title="No products"
      description="No products found for current filters."
    />

    <Card v-else>
      <CardContent class="pt-6">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead class="text-right">Sale Price</TableHead>
              <TableHead>Status</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="product in products" :key="product.id">
              <TableCell>
                <RouterLink :to="`/products/${product.id}`" class="font-medium hover:underline">
                  {{ product.name }}
                </RouterLink>
              </TableCell>
              <TableCell>{{ product.sku }}</TableCell>
              <TableCell class="text-right">{{ formatCurrency(product.sale_price) }}</TableCell>
              <TableCell>
                <StatusBadge :status="product.is_active ? 'active' : 'archived'" />
              </TableCell>
              <TableCell class="text-right">
                <ConfirmActionDialog
                  v-if="product.is_active"
                  title="Archive product"
                  description="Archived products remain visible but inactive."
                  confirm-label="Archive"
                  @confirm="archiveMutation.mutate(product.id)"
                >
                  <template #trigger>
                    <Button variant="outline" size="sm">Archive</Button>
                  </template>
                </ConfirmActionDialog>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>

    <ServerPagination
      v-if="meta"
      :current-page="meta.current_page"
      :total-pages="meta.last_page"
      @update:page="(nextPage) => (page = nextPage)"
    />
  </section>
</template>
