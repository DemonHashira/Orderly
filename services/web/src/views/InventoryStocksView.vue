<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useInventoryStocksQuery } from '@/features/inventory/composables/useInventoryQueries'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  DebouncedSearchInput,
  EmptyStateCard,
  PageInitialSkeleton,
  PageHeader,
  PageRefetchOverlay,
  ServerPagination,
} from '@/shared/ui'

const page = ref(1)
const search = ref('')
const debouncedSearch = useDebouncedRef(search)

const stocksQuery = useInventoryStocksQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    q: debouncedSearch.value,
  })),
)

const stocks = computed(() => stocksQuery.data.value?.data ?? [])
const meta = computed(() => stocksQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(stocksQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && stocksQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Inventory Stocks"
      description="View current on-hand, reserved, and available stock."
    />

    <Card>
      <CardContent class="pt-6">
        <DebouncedSearchInput v-model="search" placeholder="Search by SKU or product" />
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="stocksQuery.error.value" message="Failed to load inventory stocks." />

    <Card>
      <CardContent class="pt-6">
        <EmptyStateCard
          v-if="!stocksQuery.isLoading.value && stocks.length === 0"
          title="No stock records"
          description="No inventory stocks match your filters."
        />

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead class="text-right">On Hand</TableHead>
              <TableHead class="text-right">Reserved</TableHead>
              <TableHead class="text-right">Available</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="stock in stocks" :key="stock.product.id">
              <TableCell>
                <RouterLink
                  :to="`/products/${stock.product.id}`"
                  class="font-medium hover:underline"
                >
                  {{ stock.product.name }}
                </RouterLink>
              </TableCell>
              <TableCell>{{ stock.product.sku }}</TableCell>
              <TableCell class="text-right">{{ stock.qty_on_hand }}</TableCell>
              <TableCell class="text-right">{{ stock.qty_reserved }}</TableCell>
              <TableCell class="text-right">{{ stock.available }}</TableCell>
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
