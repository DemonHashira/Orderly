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
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import { useOrderQuery } from '@/features/orders/composables/useOrdersQueries'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'

const route = useRoute()
const orderId = computed(() => Number(route.params.id))

const orderQuery = useOrderQuery(orderId)
const order = computed(() => orderQuery.data.value?.data)
const isInitialLoading = useInitialLoadingGate(orderQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && orderQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Order Detail" description="Inspect order data, items, and status history." />

    <ApiErrorAlert v-if="orderQuery.error.value" message="Failed to load this order." />

    <template v-else-if="order">
      <div class="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{{ order.reference }}</CardTitle>
            <CardDescription> Created {{ formatDateTime(order.created_at) }} </CardDescription>
          </CardHeader>
          <CardContent class="space-y-2 text-sm">
            <p>
              <span class="font-medium">Status:</span>
              <StatusBadge :status="order.current_status" />
            </p>
            <p><span class="font-medium">Customer ID:</span> {{ order.customer_id }}</p>
            <p><span class="font-medium">Sales Channel ID:</span> {{ order.sales_channel_id }}</p>
            <p><span class="font-medium">Total:</span> {{ formatCurrency(order.total_amount) }}</p>
            <p>
              <span class="font-medium">Internal notes:</span> {{ order.internal_notes ?? '-' }}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Status Timeline</CardTitle>
            <CardDescription>Latest status changes.</CardDescription>
          </CardHeader>
          <CardContent>
            <EmptyStateCard
              v-if="!order.status_history || order.status_history.length === 0"
              title="No status history"
              description="No transitions recorded yet."
            />
            <ul v-else class="space-y-2 text-sm">
              <li
                v-for="status in order.status_history"
                :key="status.id"
                class="border-b pb-2 last:border-b-0"
              >
                <div class="flex items-center justify-between gap-2">
                  <StatusBadge :status="status.status" />
                  <span class="text-muted-foreground">{{ formatDateTime(status.changed_at) }}</span>
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
              <TableRow v-for="item in order.items ?? []" :key="item.id">
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
  </section>
</template>
