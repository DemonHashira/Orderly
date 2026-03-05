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
  const cards = ['orders-total', 'returns-total', 'orders-revenue']
    .map((id) => page.baseKpiCards.value[id])
    .filter((card): card is NonNullable<typeof card> => card != null)

  cards.push({
    id: 'follow-up-total',
    title: 'Follow-up Shipments',
    value: formatNumber(page.followUpShipments.value.length),
    description: 'Returned/unpaid shipments needing action',
  })

  return cards
})

const chartCards = computed(() =>
  (['orders-by-status', 'returns-by-outcome'] as const)
    .map((id) => page.baseChartCards.value[id])
    .filter((card): card is NonNullable<typeof card> => card != null),
)

const queueOrder = computed(() => {
  const entries: Array<
    'ready-to-ship' | 'shipment-follow-up' | 'returns-to-restock' | 'inventory-attention'
  > = []

  if (page.queuePermissions.canViewShipments.value) entries.push('shipment-follow-up')
  if (page.queuePermissions.canViewOrders.value) entries.push('ready-to-ship')

  return entries
})
</script>

<template>
  <PageInitialSkeleton v-if="page.isInitialLoading.value" />

  <section v-else class="relative space-y-6">
    <PageRefetchOverlay :show="page.isRefetching.value" />
    <PageHeader
      title="Logistics Dashboard"
      description="Shipment outcomes, follow-ups, and handoff readiness."
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
      <Card v-if="page.queuePermissions.canViewRestocks.value" class="dashboard-card-interactive">
        <CardHeader>
          <CardTitle>Logistics Action Checklist</CardTitle>
          <CardDescription
            >Recommended daily sequence for fulfillment status updates.</CardDescription
          >
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <p>1. Process follow-up outcomes first (returned/unpaid).</p>
          <p>2. Clear ready-to-ship queue by creating shipments.</p>
          <p>3. Keep tracking and courier data complete.</p>
        </CardContent>
      </Card>

      <Card class="dashboard-card-interactive">
        <CardHeader>
          <CardTitle>Returns Visibility</CardTitle>
          <CardDescription>Recent returns to coordinate with inventory team.</CardDescription>
        </CardHeader>
        <CardContent>
          <div
            v-if="page.returnsToRestock.value.length === 0"
            class="text-muted-foreground text-sm"
          >
            No pending returns in this range.
          </div>
          <ul v-else class="space-y-2 text-sm">
            <li
              v-for="item in page.returnsToRestock.value.slice(0, 3)"
              :key="item.id"
              class="flex justify-between gap-2"
            >
              <RouterLink :to="`/returns/${item.id}`" class="font-medium hover:underline">
                {{ item.order?.reference ?? `#${item.order_id}` }}
              </RouterLink>
              <span class="text-muted-foreground">{{ item.reason ?? 'No reason' }}</span>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>

    <div v-if="page.queuePermissions.canViewShipments.value" class="flex items-center justify-end">
      <Button as-child variant="outline">
        <RouterLink to="/shipments">Open Shipments Workspace</RouterLink>
      </Button>
    </div>
  </section>
</template>
