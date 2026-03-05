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
          <CardDescription>Daily stock control focus areas.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <p>1. Resolve low-availability SKUs first.</p>
          <p>2. Process restockable returns quickly.</p>
          <p>3. Verify adjustments with movement history.</p>
        </CardContent>
      </Card>

      <Card class="dashboard-card-interactive">
        <CardHeader>
          <CardTitle>Movement Visibility</CardTitle>
          <CardDescription>Fast access to stock movement history and controls.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <Button as-child variant="outline" size="sm">
            <RouterLink to="/inventory/movements">Open Inventory Movements</RouterLink>
          </Button>
          <Button as-child variant="outline" size="sm">
            <RouterLink to="/inventory/stocks">Open Inventory Stocks</RouterLink>
          </Button>
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
