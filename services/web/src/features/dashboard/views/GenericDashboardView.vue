<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DateRangeFilter, PageHeader, PageInitialSkeleton, PageRefetchOverlay } from '@/shared/ui'
import { useDashboardPageData } from '@/features/dashboard/composables/useDashboardPageData'
import DashboardKpiSection from '@/features/dashboard/ui/DashboardKpiSection.vue'
import DashboardChartsSection from '@/features/dashboard/ui/DashboardChartsSection.vue'
import DashboardQueuesSection from '@/features/dashboard/ui/DashboardQueuesSection.vue'

const page = useDashboardPageData()

const kpiCards = computed(() =>
  [
    page.baseKpiCards.value['orders-total'],
    page.baseKpiCards.value['inventory-low-stock'],
    page.baseKpiCards.value['returns-total'],
  ].filter((card): card is NonNullable<typeof card> => card != null),
)

const chartCards = computed(() =>
  [
    page.baseChartCards.value['orders-by-status'],
    page.baseChartCards.value['returns-by-outcome'],
    page.baseChartCards.value['inventory-flow'],
  ].filter((card): card is NonNullable<typeof card> => card != null),
)

const queueOrder = computed(() => {
  const entries: Array<
    'ready-to-ship' | 'shipment-follow-up' | 'returns-to-restock' | 'inventory-attention'
  > = []

  if (
    page.queuePermissions.canViewOrders.value &&
    (page.readyOrders.value.length > 0 ||
      page.queueLoading.value.readyToShip ||
      page.queueErrors.value.readyToShip)
  ) {
    entries.push('ready-to-ship')
  }

  if (
    page.queuePermissions.canViewShipments.value &&
    (page.followUpShipments.value.length > 0 ||
      page.queueLoading.value.shipmentFollowUp ||
      page.queueErrors.value.shipmentFollowUp)
  ) {
    entries.push('shipment-follow-up')
  }

  if (
    page.queuePermissions.canViewRestocks.value &&
    (page.returnsToRestock.value.length > 0 ||
      page.queueLoading.value.returnsToRestock ||
      page.queueErrors.value.returnsToRestock)
  ) {
    entries.push('returns-to-restock')
  }

  if (
    page.queuePermissions.canViewInventory.value &&
    (page.lowAvailabilityStocks.value.length > 0 ||
      page.queueLoading.value.inventoryAttention ||
      page.queueErrors.value.inventoryAttention)
  ) {
    entries.push('inventory-attention')
  }

  if (entries.length > 0) {
    return entries
  }

  if (page.queuePermissions.canViewOrders.value) {
    entries.push('ready-to-ship')
  }
  if (page.queuePermissions.canViewShipments.value) {
    entries.push('shipment-follow-up')
  }
  if (page.queuePermissions.canViewRestocks.value) {
    entries.push('returns-to-restock')
  }
  if (page.queuePermissions.canViewInventory.value) {
    entries.push('inventory-attention')
  }

  return entries
})

const hasWorkspaceShortcuts = computed(
  () =>
    page.queuePermissions.canViewOrders.value ||
    page.queuePermissions.canViewShipments.value ||
    page.queuePermissions.canViewReturns.value ||
    page.queuePermissions.canViewInventory.value,
)
</script>

<template>
  <PageInitialSkeleton v-if="page.isInitialLoading.value" />

  <section v-else class="relative space-y-6">
    <PageRefetchOverlay :show="page.isRefetching.value" />
    <PageHeader
      title="Dashboard"
      description="Operational overview with role-aware queues and key metrics."
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

    <Card v-if="hasWorkspaceShortcuts" class="dashboard-card-interactive">
      <CardHeader>
        <CardTitle>Workspace Shortcuts</CardTitle>
        <CardDescription>Quick access to your most used modules.</CardDescription>
      </CardHeader>
      <CardContent class="flex flex-wrap gap-2">
        <Button
          v-if="page.queuePermissions.canViewOrders.value"
          as-child
          variant="outline"
          size="sm"
        >
          <RouterLink to="/orders">Orders</RouterLink>
        </Button>
        <Button
          v-if="page.queuePermissions.canViewShipments.value"
          as-child
          variant="outline"
          size="sm"
        >
          <RouterLink to="/shipments">Shipments</RouterLink>
        </Button>
        <Button
          v-if="page.queuePermissions.canViewReturns.value"
          as-child
          variant="outline"
          size="sm"
        >
          <RouterLink to="/returns">Returns</RouterLink>
        </Button>
        <Button
          v-if="page.queuePermissions.canViewInventory.value"
          as-child
          variant="outline"
          size="sm"
        >
          <RouterLink to="/inventory/stocks">Inventory</RouterLink>
        </Button>
      </CardContent>
    </Card>
  </section>
</template>
