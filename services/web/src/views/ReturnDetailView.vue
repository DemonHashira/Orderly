<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { formatDateTime } from '@/lib/formatters'
import { useReturnQuery } from '@/features/returns/composables/useReturnsQueries'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import { ApiErrorAlert, PageHeader, PageInitialSkeleton, PageRefetchOverlay } from '@/shared/ui'

const route = useRoute()
const returnId = computed(() => Number(route.params.id))

const returnQuery = useReturnQuery(returnId)
const returnOrder = computed(() => returnQuery.data.value?.data)
const isInitialLoading = useInitialLoadingGate(returnQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && returnQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Return Detail" description="Review returned items and restockability." />

    <ApiErrorAlert v-if="returnQuery.error.value" message="Unable to load return detail." />

    <template v-else-if="returnOrder">
      <Card>
        <CardHeader>
          <CardTitle>{{ returnOrder.order?.reference ?? `Return #${returnOrder.id}` }}</CardTitle>
          <CardDescription
            >Returned at {{ formatDateTime(returnOrder.returned_at) }}</CardDescription
          >
        </CardHeader>
        <CardContent class="text-sm">
          <p><span class="font-medium">Reason:</span> {{ returnOrder.reason ?? '-' }}</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Return Items</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Product</TableHead>
                <TableHead>SKU</TableHead>
                <TableHead class="text-right">Qty</TableHead>
                <TableHead class="text-right">Restockable</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="item in returnOrder.items ?? []" :key="item.id">
                <TableCell>{{ item.product?.name ?? `#${item.product_id}` }}</TableCell>
                <TableCell>{{ item.product?.sku ?? '-' }}</TableCell>
                <TableCell class="text-right">{{ item.quantity }}</TableCell>
                <TableCell class="text-right">{{ item.restockable ? 'Yes' : 'No' }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </template>
  </section>
</template>
