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
  const cards = ['orders-total', 'orders-revenue', 'returns-total']
    .map((id) => page.baseKpiCards.value[id])
    .filter((card): card is NonNullable<typeof card> => card != null)

  cards.push({
    id: 'ready-to-ship-total',
    title: 'Ready to Ship Queue',
    value: formatNumber(page.readyOrders.value.length),
    description: 'Orders currently waiting for logistics handoff',
  })

  return cards
})

const chartCards = computed(() =>
  (['orders-by-status', 'returns-by-outcome'] as const)
    .map((id) => page.baseChartCards.value[id])
    .filter((card): card is NonNullable<typeof card> => card != null),
)

const recentRestockCandidates = computed(() => page.returnsToRestock.value.slice(0, 3))

const queueOrder = computed(() => {
  const entries: Array<
    'ready-to-ship' | 'shipment-follow-up' | 'returns-to-restock' | 'inventory-attention'
  > = []

  if (page.queuePermissions.canViewOrders.value) entries.push('ready-to-ship')
  if (page.queuePermissions.canViewShipments.value) entries.push('shipment-follow-up')

  return entries
})
</script>

<template>
  <PageInitialSkeleton v-if="page.isInitialLoading.value" />

  <section v-else class="relative space-y-6">
    <PageRefetchOverlay :show="page.isRefetching.value" />
    <PageHeader
      title="Orders Dashboard"
      description="Order intake, readiness, and handoff monitoring for order managers."
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
          <CardTitle>Workflow Focus</CardTitle>
          <CardDescription>Daily handoff priorities for order operations.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <p>1. Confirm drafts and prepare ready-to-ship handoff.</p>
          <p>2. Follow up unpaid and returned outcomes quickly.</p>
          <p>3. Keep customer/order details clean before shipping.</p>
        </CardContent>
      </Card>

      <Card class="dashboard-card-interactive">
        <CardHeader>
          <CardTitle>Recent Restock Candidates</CardTitle>
          <CardDescription>Recent return records that may affect replacements.</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="recentRestockCandidates.length === 0" class="text-muted-foreground text-sm">
            No recent return candidates.
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
        </CardContent>
      </Card>
    </div>

    <div v-if="page.queuePermissions.canViewOrders.value" class="flex items-center justify-end">
      <Button as-child variant="outline">
        <RouterLink to="/orders">Open Orders Workspace</RouterLink>
      </Button>
    </div>
  </section>
</template>
