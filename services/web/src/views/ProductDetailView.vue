<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useProductQuery } from '@/features/products/composables/useProductsQueries'
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'

const route = useRoute()
const productId = computed(() => Number(route.params.id))

const productQuery = useProductQuery(productId)
const product = computed(() => productQuery.data.value?.data)
const isInitialLoading = useInitialLoadingGate(productQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && productQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Product Detail" description="Product metadata and pricing snapshot." />

    <ApiErrorAlert v-if="productQuery.error.value" message="Unable to load product." />

    <Card v-else-if="product">
      <CardHeader>
        <CardTitle>{{ product.name }}</CardTitle>
        <CardDescription>SKU {{ product.sku }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-2 text-sm">
        <p><span class="font-medium">Sale price:</span> {{ formatCurrency(product.sale_price) }}</p>
        <p><span class="font-medium">Description:</span> {{ product.description ?? '-' }}</p>
        <p>
          <span class="font-medium">Status:</span>
          <StatusBadge :status="product.is_active ? 'active' : 'archived'" />
        </p>
        <p><span class="font-medium">Created:</span> {{ formatDateTime(product.created_at) }}</p>
      </CardContent>
    </Card>
  </section>
</template>
