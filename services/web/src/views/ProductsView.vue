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

const page = ref(1)
const search = ref('')
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

    <Card>
      <CardContent class="pt-6">
        <EmptyStateCard
          v-if="!productsQuery.isLoading.value && products.length === 0"
          title="No products"
          description="No products found for current filters."
        />

        <Table v-else>
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
