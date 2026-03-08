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

const returnOutcomeCounts = computed(() => page.dashboardData.value?.returns?.by_order_status ?? {})
const recentRestockCandidates = computed(() => page.returnsToRestock.value.slice(0, 4))

const kpiCards = computed(() => [
  {
    id: 'follow-up-total',
    title: 'Follow-up Shipments',
    value: formatNumber(page.followUpShipments.value.length),
    description: 'Returned/unpaid shipments needing action',
  },
  {
    id: 'ready-to-ship-total',
    title: 'Ready to Ship Queue',
    value: formatNumber(page.readyOrders.value.length),
    description: 'Orders currently waiting for logistics handoff',
  },
  {
    id: 'returned-outcomes-total',
    title: 'Returned Outcomes',
    value: formatNumber(returnOutcomeCounts.value.returned ?? 0),
    description: 'Orders currently in returned outcome',
  },
  {
    id: 'unpaid-outcomes-total',
    title: 'Unpaid Outcomes',
    value: formatNumber(returnOutcomeCounts.value.unpaid ?? 0),
    description: 'Orders currently in unpaid outcome',
  },
])

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
    >
      <template #companion>
        <Card class="dashboard-card-interactive">
          <CardHeader>
            <CardTitle>Recent Restock Candidates</CardTitle>
            <CardDescription>Recent returns to coordinate with inventory team.</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="recentRestockCandidates.length === 0" class="text-muted-foreground text-sm">
              No pending returns in this range.
            </div>
            <ul v-else class="space-y-2 text-sm">
              <li
                v-for="item in recentRestockCandidates"
                :key="item.id"
                class="flex justify-between gap-2"
              >
                <RouterLink :to="`/returns/${item.id}`" class="font-medium hover:underline">
                  {{ item.order?.reference ?? `#${item.order_id}` }}
                </RouterLink>
                <span class="text-muted-foreground">{{ item.reason ?? 'No reason' }}</span>
              </li>
            </ul>
            <p class="text-muted-foreground mt-3 text-xs">Showing latest 4 records.</p>
          </CardContent>
        </Card>
      </template>
    </DashboardChartsSection>

    <DashboardQueuesSection
      :queue-order="queueOrder"
      :ready-orders="page.readyOrders.value"
      :returns-to-restock="page.returnsToRestock.value"
      :follow-up-shipments="page.followUpShipments.value"
      :low-availability-stocks="page.lowAvailabilityStocks.value"
      :queue-loading="page.queueLoading.value"
      :queue-errors="page.queueErrors.value"
    />

    <div v-if="page.queuePermissions.canViewShipments.value" class="flex items-center justify-end">
      <Button as-child variant="outline">
        <RouterLink to="/shipments">Open Shipments Workspace</RouterLink>
      </Button>
    </div>
  </section>
</template>
