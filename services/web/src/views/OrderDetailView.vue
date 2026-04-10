<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
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
import { useAuth } from '@/features/auth/composables/useAuth'
import {
  useDeleteOrderMutation,
  useOrderQuery,
} from '@/features/orders/composables/useOrdersQueries'
import { normalizeApiError } from '@/shared/api/errors'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  ConfirmActionDialog,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  OverflowTooltipText,
  StatusBadge,
} from '@/shared/ui'

const route = useRoute()
const router = useRouter()
const { permissions } = useAuth()
const deleteError = ref('')

const orderId = computed(() => Number(route.params.id))
const orderQuery = useOrderQuery(orderId)
const deleteMutation = useDeleteOrderMutation()
const order = computed(() => orderQuery.data.value?.data)
const isInitialLoading = useInitialLoadingGate(orderQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && orderQuery.isFetching.value)
const isDraftOrder = computed(() => order.value?.current_status === 'draft')
const canEdit = computed(() => permissions.value.includes('orders.update') && isDraftOrder.value)
const canDelete = computed(() => permissions.value.includes('orders.delete') && isDraftOrder.value)

const onDelete = async () => {
  if (!order.value) {
    return
  }

  deleteError.value = ''

  try {
    await deleteMutation.mutateAsync(order.value.id)
    await router.push('/orders')
  } catch (error: unknown) {
    deleteError.value = normalizeApiError(error).message
  }
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Order Detail" description="Inspect order data, items, and status history.">
      <template #actions>
        <Button v-if="canEdit && order" as-child variant="outline" size="sm">
          <RouterLink :to="`/orders/${order.id}/edit`">Edit Order</RouterLink>
        </Button>
        <ConfirmActionDialog
          v-if="canDelete && order"
          title="Delete draft order"
          description="This will permanently delete the draft order."
          confirm-label="Delete Order"
          @confirm="onDelete"
        >
          <template #trigger>
            <Button variant="destructive" size="sm">Delete Order</Button>
          </template>
        </ConfirmActionDialog>
      </template>
    </PageHeader>

    <ApiErrorAlert v-if="orderQuery.error.value" message="Failed to load this order." />
    <ApiErrorAlert v-if="deleteError" :message="deleteError" />

    <template v-else-if="order">
      <div class="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{{ order.reference }}</CardTitle>
            <CardDescription> Created {{ formatDateTime(order.created_at) }} </CardDescription>
          </CardHeader>
          <CardContent class="space-y-2 text-sm">
            <div class="inline-flex items-center gap-2">
              <span class="font-medium">Status:</span>
              <StatusBadge :status="order.current_status" />
            </div>
            <p>
              <span class="font-medium">Customer:</span>
              {{ order.customer_name ?? `Customer #${order.customer_id}` }}
            </p>
            <p>
              <span class="font-medium">Sales Channel:</span>
              {{ order.sales_channel_name ?? `Channel #${order.sales_channel_id}` }}
            </p>
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
          <CardContent v-if="!order.status_history || order.status_history.length === 0">
            <p class="text-sm text-muted-foreground">No transitions recorded yet.</p>
          </CardContent>
          <CardContent v-else>
            <ul class="space-y-2 text-sm">
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
                <TableHead>Product</TableHead>
                <TableHead class="text-right">Qty</TableHead>
                <TableHead class="text-right">Unit Price</TableHead>
                <TableHead class="text-right">Total</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="item in order.items ?? []" :key="item.id">
                <TableCell>
                  <div v-if="item.product?.name" class="max-w-[14rem] space-y-1 sm:max-w-[18rem]">
                    <OverflowTooltipText
                      :text="item.product.name"
                      data-test="tooltip-trigger"
                      trigger-class="font-medium text-foreground"
                    />
                    <p class="text-xs text-muted-foreground">
                      ID #{{ item.product_id }}
                      <span v-if="item.product.sku" class="ml-2">SKU {{ item.product.sku }}</span>
                    </p>
                  </div>
                  <div v-else class="space-y-1">
                    <p class="font-medium text-foreground">Product #{{ item.product_id }}</p>
                    <p class="text-xs text-muted-foreground">ID #{{ item.product_id }}</p>
                  </div>
                </TableCell>
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
