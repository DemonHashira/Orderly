<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DateRangeFilter, PageHeader, PageInitialSkeleton, PageRefetchOverlay } from '@/shared/ui'
import { formatNumber } from '@/lib/formatters'
import { useDashboardPageData } from '@/features/dashboard/composables/useDashboardPageData'
import DashboardKpiSection from '@/features/dashboard/ui/DashboardKpiSection.vue'
import DashboardChartsSection from '@/features/dashboard/ui/DashboardChartsSection.vue'
import DashboardQueuesSection from '@/features/dashboard/ui/DashboardQueuesSection.vue'

const page = useDashboardPageData()
const inventorySummary = computed(() => page.dashboardData.value?.inventory)
const movementInQty = computed(() => inventorySummary.value?.movement_in_qty ?? 0)
const movementOutQty = computed(() => inventorySummary.value?.movement_out_qty ?? 0)
const netMovementQty = computed(() => movementInQty.value - movementOutQty.value)
const priorityStocks = computed(() => page.lowAvailabilityStocks.value.slice(0, 3))
const lowestAvailabilityStock = computed(() => priorityStocks.value[0] ?? null)
const outflowRatio = computed(() => {
  if (movementInQty.value <= 0) {
    return 0
  }
  return (movementOutQty.value / movementInQty.value) * 100
})

const kpiCards = computed(() => {
  const cards = ['inventory-low-stock', 'inventory-available', 'returns-total']
    .map((id) => page.baseKpiCards.value[id])
    .filter((card): card is NonNullable<typeof card> => card != null)

  cards.push({
    id: 'restock-queue-total',
    title: 'Restock Queue',
    value: formatNumber(page.returnsToRestock.value.length),
    description: 'Returns pending restock decisions',
  })

  return cards
})

const chartCards = computed(() =>
  (['inventory-flow', 'returns-by-outcome'] as const)
    .map((id) => page.baseChartCards.value[id])
    .filter((card): card is NonNullable<typeof card> => card != null),
)

const queueOrder = computed(() => {
  const entries: Array<
    'ready-to-ship' | 'shipment-follow-up' | 'returns-to-restock' | 'inventory-attention'
  > = []

  if (page.queuePermissions.canViewInventory.value) entries.push('inventory-attention')
  if (page.queuePermissions.canViewRestocks.value) entries.push('returns-to-restock')

  return entries
})
</script>

<template>
  <PageInitialSkeleton v-if="page.isInitialLoading.value" />

  <section v-else class="relative space-y-6">
    <PageRefetchOverlay :show="page.isRefetching.value" />
    <PageHeader
      title="Inventory Dashboard"
      description="Stock accuracy, movement flow, and restock operations."
    >
      <template #actions>
        <DateRangeFilter
          :from="page.from.value"
          :to="page.to.value"
          @preset="page.onPreset"
          @update:from="(value) => page.updateQuery({ from: value || undefined })"
          @update:to="(value) => page.updateQuery({ to: value || undefined })"
        />
      </template>
    </PageHeader>

    <DashboardKpiSection
      :cards="kpiCards"
      :is-loading="page.dashboardQuery.isLoading.value"
      :has-error="Boolean(page.dashboardQuery.error.value)"
    />

    <DashboardChartsSection
      :charts="chartCards"
      :has-error="Boolean(page.dashboardQuery.error.value)"
    />

    <DashboardQueuesSection
      :queue-order="queueOrder"
      :ready-orders="page.readyOrders.value"
      :returns-to-restock="page.returnsToRestock.value"
      :follow-up-shipments="page.followUpShipments.value"
      :low-availability-stocks="page.lowAvailabilityStocks.value"
      :queue-loading="page.queueLoading.value"
      :queue-errors="page.queueErrors.value"
    />

    <div class="grid gap-4 xl:grid-cols-2">
      <Card v-if="page.queuePermissions.canViewInventory.value" class="dashboard-card-interactive">
        <CardHeader>
          <CardTitle>Inventory Priorities</CardTitle>
          <CardDescription
            >Top low-availability SKUs requiring immediate attention.</CardDescription
          >
        </CardHeader>
        <CardContent>
          <div v-if="priorityStocks.length === 0" class="text-muted-foreground text-sm">
            No low-availability SKUs in the current range.
          </div>
          <ul v-else class="space-y-2 text-sm">
            <li
              v-for="stock in priorityStocks"
              :key="stock.product.id"
              class="flex items-center justify-between gap-2"
            >
              <div class="min-w-0">
                <p class="truncate font-medium">{{ stock.product.name }}</p>
                <p class="text-muted-foreground text-xs">{{ stock.product.sku }}</p>
              </div>
              <p class="text-sm font-medium">{{ formatNumber(stock.available) }}</p>
            </li>
          </ul>
          <p v-if="lowestAvailabilityStock" class="text-muted-foreground mt-3 text-xs">
            Lowest availability: {{ lowestAvailabilityStock.product.sku }} ({{
              formatNumber(lowestAvailabilityStock.available)
            }}
            units)
          </p>
          <div class="mt-4">
            <Button as-child variant="outline" size="sm">
              <RouterLink to="/inventory/stocks">Review Inventory Stocks</RouterLink>
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card class="dashboard-card-interactive">
        <CardHeader>
          <CardTitle>Movement Visibility</CardTitle>
          <CardDescription
            >Track flow direction and open movement controls quickly.</CardDescription
          >
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-3 gap-3 text-sm">
            <div class="rounded-md border p-3">
              <p class="text-muted-foreground text-xs">In</p>
              <p class="text-base font-semibold">{{ formatNumber(movementInQty) }}</p>
            </div>
            <div class="rounded-md border p-3">
              <p class="text-muted-foreground text-xs">Out</p>
              <p class="text-base font-semibold">{{ formatNumber(movementOutQty) }}</p>
            </div>
            <div class="rounded-md border p-3">
              <p class="text-muted-foreground text-xs">Net</p>
              <p class="text-base font-semibold">{{ formatNumber(netMovementQty) }}</p>
            </div>
          </div>
          <div class="rounded-md border p-3 text-sm">
            <p class="text-muted-foreground text-xs">Outflow Ratio</p>
            <p class="font-medium">{{ outflowRatio.toFixed(1) }}% of inbound flow</p>
          </div>
          <div class="flex flex-wrap gap-3">
            <Button as-child size="sm">
              <RouterLink to="/inventory/movements">Open Inventory Movements</RouterLink>
            </Button>
            <Button as-child variant="outline" size="sm">
              <RouterLink to="/inventory/stocks">Open Inventory Stocks</RouterLink>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <div v-if="page.queuePermissions.canViewInventory.value" class="flex items-center justify-end">
      <Button as-child variant="outline">
        <RouterLink to="/inventory/stocks">Open Inventory Workspace</RouterLink>
      </Button>
    </div>
  </section>
</template>
