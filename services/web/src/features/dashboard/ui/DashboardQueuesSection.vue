<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { DashboardQueueBlockId } from '@/features/dashboard/model'
import { formatCurrency, formatNumber } from '@/lib/formatters'
import type { InventoryStock, Order, ReturnOrder, Shipment } from '@/types'
import { ApiErrorAlert, EmptyStateCard, StatusBadge } from '@/shared/ui'

const props = defineProps<{
  queueOrder: DashboardQueueBlockId[]
  readyOrders: Order[]
  returnsToRestock: ReturnOrder[]
  followUpShipments: Shipment[]
  lowAvailabilityStocks: InventoryStock[]
  queueLoading: {
    readyToShip: boolean
    returnsToRestock: boolean
    shipmentFollowUp: boolean
    inventoryAttention: boolean
  }
  queueErrors: {
    readyToShip: boolean
    returnsToRestock: boolean
    shipmentFollowUp: boolean
    inventoryAttention: boolean
  }
}>()

const cardMeta = computed<
  Record<DashboardQueueBlockId, { title: string; description: string; to: string }>
>(() => ({
  'ready-to-ship': {
    title: 'Ready To Ship',
    description: 'Orders waiting for logistics handoff.',
    to: '/orders?status=ready_to_ship',
  },
  'returns-to-restock': {
    title: 'Returns to Restock',
    description: 'Returned items marked restockable.',
    to: '/returns?has_restockable=true',
  },
  'shipment-follow-up': {
    title: 'Shipment Follow-up',
    description: 'Returned and unpaid shipment outcomes.',
    to: '/shipments',
  },
  'inventory-attention': {
    title: 'Inventory Attention',
    description: 'Lowest currently available SKUs.',
    to: '/inventory/stocks',
  },
}))

const gridClass = computed(() => {
  if (props.queueOrder.length >= 2) return 'grid gap-4 xl:grid-cols-2'
  return 'grid gap-4 grid-cols-1'
})
</script>

<template>
  <Transition mode="out-in" name="dashboard-section">
    <TransitionGroup
      v-if="props.queueOrder.length > 0"
      key="queues-ready"
      name="dashboard-grid"
      tag="div"
      :class="gridClass"
    >
      <Card
        v-for="queueId in props.queueOrder"
        :key="queueId"
        class="dashboard-card-interactive flex h-full flex-col"
      >
        <CardHeader>
          <CardTitle>{{ cardMeta[queueId].title }}</CardTitle>
          <CardDescription>{{ cardMeta[queueId].description }}</CardDescription>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col">
          <template v-if="queueId === 'ready-to-ship'">
            <ApiErrorAlert
              v-if="props.queueErrors.readyToShip"
              message="Ready-to-ship queue could not be loaded."
            />
            <div v-else-if="props.queueLoading.readyToShip" class="space-y-2">
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-4/5" />
              <Skeleton class="h-4 w-3/5" />
            </div>
            <EmptyStateCard
              v-else-if="props.readyOrders.length === 0"
              title="No ready orders"
              description="No orders currently in ready_to_ship status."
            />
            <Table v-else>
              <TableHeader>
                <TableRow>
                  <TableHead>Reference</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="order in props.readyOrders" :key="order.id">
                  <TableCell>
                    <RouterLink :to="`/orders/${order.id}`" class="font-medium hover:underline">
                      {{ order.reference }}
                    </RouterLink>
                  </TableCell>
                  <TableCell><StatusBadge :status="order.current_status" /></TableCell>
                  <TableCell class="text-right">{{ formatCurrency(order.total_amount) }}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </template>

          <template v-else-if="queueId === 'returns-to-restock'">
            <ApiErrorAlert
              v-if="props.queueErrors.returnsToRestock"
              message="Returns-to-restock queue could not be loaded."
            />
            <div v-else-if="props.queueLoading.returnsToRestock" class="space-y-2">
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-4/5" />
              <Skeleton class="h-4 w-3/5" />
            </div>
            <EmptyStateCard
              v-else-if="props.returnsToRestock.length === 0"
              title="No pending restocks"
              description="No restockable returns found."
            />
            <Table v-else>
              <TableHeader>
                <TableRow>
                  <TableHead>Order</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead class="text-right">Items</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="returnOrder in props.returnsToRestock" :key="returnOrder.id">
                  <TableCell>
                    <RouterLink
                      :to="`/returns/${returnOrder.id}`"
                      class="font-medium hover:underline"
                    >
                      {{ returnOrder.order?.reference ?? `#${returnOrder.order_id}` }}
                    </RouterLink>
                  </TableCell>
                  <TableCell>{{ returnOrder.reason ?? '-' }}</TableCell>
                  <TableCell class="text-right">{{ returnOrder.items?.length ?? 0 }}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </template>

          <template v-else-if="queueId === 'shipment-follow-up'">
            <ApiErrorAlert
              v-if="props.queueErrors.shipmentFollowUp"
              message="Shipment follow-up queue could not be loaded."
            />
            <div v-else-if="props.queueLoading.shipmentFollowUp" class="space-y-2">
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-4/5" />
              <Skeleton class="h-4 w-3/5" />
            </div>
            <EmptyStateCard
              v-else-if="props.followUpShipments.length === 0"
              title="No follow-up shipments"
              description="No unpaid or returned shipment outcomes right now."
            />
            <Table v-else>
              <TableHeader>
                <TableRow>
                  <TableHead>Order</TableHead>
                  <TableHead>Courier</TableHead>
                  <TableHead>Outcome</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="shipment in props.followUpShipments" :key="shipment.id">
                  <TableCell>
                    <RouterLink
                      :to="`/shipments/${shipment.id}`"
                      class="font-medium hover:underline"
                    >
                      {{ shipment.order?.reference ?? `#${shipment.order_id}` }}
                    </RouterLink>
                  </TableCell>
                  <TableCell>{{ shipment.courier }}</TableCell>
                  <TableCell>
                    <StatusBadge :status="shipment.order?.current_status ?? 'pending'" />
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </template>

          <template v-else-if="queueId === 'inventory-attention'">
            <ApiErrorAlert
              v-if="props.queueErrors.inventoryAttention"
              message="Inventory attention queue could not be loaded."
            />
            <div v-else-if="props.queueLoading.inventoryAttention" class="space-y-2">
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-4/5" />
              <Skeleton class="h-4 w-3/5" />
            </div>
            <EmptyStateCard
              v-else-if="props.lowAvailabilityStocks.length === 0"
              title="No stock snapshots"
              description="Inventory data unavailable for this user."
            />
            <Table v-else>
              <TableHeader>
                <TableRow>
                  <TableHead>Product</TableHead>
                  <TableHead>SKU</TableHead>
                  <TableHead class="text-right">Available</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="stock in props.lowAvailabilityStocks" :key="stock.product.id">
                  <TableCell>
                    <RouterLink
                      :to="`/products/${stock.product.id}`"
                      class="font-medium hover:underline"
                    >
                      {{ stock.product.name }}
                    </RouterLink>
                  </TableCell>
                  <TableCell>{{ stock.product.sku }}</TableCell>
                  <TableCell class="text-right">{{ formatNumber(stock.available) }}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </template>

          <div class="mt-4 flex justify-end">
            <RouterLink
              :to="cardMeta[queueId].to"
              class="text-muted-foreground hover:text-foreground text-sm font-medium transition-colors"
            >
              View all
            </RouterLink>
          </div>
        </CardContent>
      </Card>
    </TransitionGroup>

    <EmptyStateCard
      v-else
      key="queues-empty"
      title="No operational queues"
      description="No queue widgets are available for your role."
    />
  </Transition>
</template>
