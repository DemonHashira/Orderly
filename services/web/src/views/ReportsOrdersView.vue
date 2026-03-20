<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useOrdersReportSummaryQuery } from '@/features/reports/composables/useOrdersReportSummaryQuery'
import { useReportDateRangeQuery } from '@/features/reports/composables/useReportDateRangeQuery'
import { buildOrdersReportViewModel } from '@/features/reports/model/report-view-models'
import type { DashboardKpiCard } from '@/features/dashboard/types'
import DashboardKpiSection from '@/features/dashboard/ui/DashboardKpiSection.vue'
import MiniDistributionChart from '@/features/dashboard/ui/MiniDistributionChart.vue'
import { formatDateTime } from '@/lib/formatters'
import { normalizeApiError } from '@/shared/api/errors'
import {
  ApiErrorAlert,
  DateRangeFilter,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
} from '@/shared/ui'

const { permissions } = useAuth()
const range = useReportDateRangeQuery()
const reportQuery = useOrdersReportSummaryQuery(
  computed(() => ({
    from: range.from.value,
    to: range.to.value,
  })),
)

const summaryResponse = computed(() => reportQuery.data.value)
const summary = computed(() => summaryResponse.value?.data)
const generatedAt = computed(() => summaryResponse.value?.meta.generated_at)
const viewModel = computed(() => (summary.value ? buildOrdersReportViewModel(summary.value) : null))
const cards = computed<DashboardKpiCard[]>(() => viewModel.value?.cards ?? [])
const isInitialLoading = computed(() => reportQuery.isLoading.value && summary.value == null)
const isRefetching = computed(() => !isInitialLoading.value && reportQuery.isFetching.value)
const errorMessage = computed(() =>
  reportQuery.error.value ? normalizeApiError(reportQuery.error.value).message : '',
)
const showErrorState = computed(() => Boolean(reportQuery.error.value) && summary.value == null)
const canOpenOrdersWorkspace = computed(() => permissions.value.includes('orders.view'))
const ordersWorkspaceLink = computed(() => ({
  path: '/orders',
  query: {
    ...(range.from.value ? { created_from: range.from.value } : {}),
    ...(range.to.value ? { created_to: range.to.value } : {}),
  },
}))
const rangeLabel = computed(() => {
  if (!summary.value) {
    return 'Selected range'
  }

  if (summary.value.range.is_all_time) {
    return 'All time'
  }

  return `${summary.value.range.from ?? '-'} to ${summary.value.range.to ?? '-'}`
})
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-6">
    <PageRefetchOverlay :show="isRefetching" />
    <PageHeader title="Orders Report" description="Revenue and status distribution across orders.">
      <template #actions>
        <DateRangeFilter
          :from="range.from.value"
          :to="range.to.value"
          @preset="range.onPreset"
          @update:from="
            (value) => range.updateQuery({ from: value || undefined, to: range.to.value })
          "
          @update:to="
            (value) => range.updateQuery({ from: range.from.value, to: value || undefined })
          "
        />
      </template>
    </PageHeader>

    <p v-if="summary" class="text-muted-foreground text-sm">
      Generated {{ formatDateTime(generatedAt) }} for {{ rangeLabel }}.
    </p>

    <ApiErrorAlert v-if="reportQuery.error.value" :message="errorMessage" />

    <EmptyStateCard
      v-if="showErrorState"
      title="Orders report unavailable"
      description="The orders summary could not be loaded for the selected range."
    />

    <template v-else-if="summary && viewModel">
      <DashboardKpiSection :cards="cards" :is-loading="false" :has-error="false" />

      <div class="grid gap-4 xl:grid-cols-2">
        <MiniDistributionChart
          chart-id="orders-by-status"
          title="Orders by Status"
          description="Status distribution within the selected range."
          :points="viewModel.chartPoints"
        />

        <Card class="dashboard-card-interactive">
          <CardHeader>
            <CardTitle>Top status</CardTitle>
            <CardDescription
              >Use this summary to confirm where order volume is concentrating.</CardDescription
            >
          </CardHeader>
          <CardContent class="space-y-4">
            <template v-if="viewModel.topStatus">
              <div class="rounded-md border p-3">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">Top status</p>
                <p class="mt-1 text-lg font-semibold capitalize">
                  {{ viewModel.topStatus.label.replace(/_/g, ' ') }}
                </p>
                <p class="text-muted-foreground text-sm">{{ viewModel.topStatus.value }} orders</p>
              </div>

              <div class="space-y-2 text-sm">
                <div
                  v-for="status in viewModel.statusBreakdown"
                  :key="status.label"
                  class="flex items-center justify-between gap-3 border-b pb-2 last:border-b-0 last:pb-0"
                >
                  <span class="capitalize">{{ status.label.replace(/_/g, ' ') }}</span>
                  <span class="font-medium">{{ status.value }}</span>
                </div>
              </div>
            </template>
            <div v-else class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
              No order activity was recorded for the selected range.
            </div>

            <div v-if="canOpenOrdersWorkspace" class="flex justify-end">
              <Button as-child variant="outline">
                <RouterLink :to="ordersWorkspaceLink">Open Orders Workspace</RouterLink>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </template>
  </section>
</template>
