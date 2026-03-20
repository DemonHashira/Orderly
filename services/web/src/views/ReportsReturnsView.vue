<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/features/auth/composables/useAuth'
import type { DashboardKpiCard } from '@/features/dashboard/types'
import DashboardKpiSection from '@/features/dashboard/ui/DashboardKpiSection.vue'
import MiniDistributionChart from '@/features/dashboard/ui/MiniDistributionChart.vue'
import { useReportDateRangeQuery } from '@/features/reports/composables/useReportDateRangeQuery'
import { useReturnsReportSummaryQuery } from '@/features/reports/composables/useReturnsReportSummaryQuery'
import { buildReturnsReportViewModel } from '@/features/reports/model/report-view-models'
import { formatDateTime, formatNumber } from '@/lib/formatters'
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
const reportQuery = useReturnsReportSummaryQuery(
  computed(() => ({
    from: range.from.value,
    to: range.to.value,
  })),
)

const summaryResponse = computed(() => reportQuery.data.value)
const summary = computed(() => summaryResponse.value?.data)
const generatedAt = computed(() => summaryResponse.value?.meta.generated_at)
const viewModel = computed(() =>
  summary.value ? buildReturnsReportViewModel(summary.value) : null,
)
const cards = computed<DashboardKpiCard[]>(() => viewModel.value?.cards ?? [])
const isInitialLoading = computed(() => reportQuery.isLoading.value && summary.value == null)
const isRefetching = computed(() => !isInitialLoading.value && reportQuery.isFetching.value)
const errorMessage = computed(() =>
  reportQuery.error.value ? normalizeApiError(reportQuery.error.value).message : '',
)
const showErrorState = computed(() => Boolean(reportQuery.error.value) && summary.value == null)
const canOpenReturnsWorkspace = computed(() => permissions.value.includes('returns.view'))
const canOpenInventoryWorkspace = computed(() => permissions.value.includes('inventory.view'))
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
    <PageHeader
      title="Returns Report"
      description="Return volume, restockability, and outcome split for the selected range."
    >
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
      title="Returns report unavailable"
      description="The returns summary could not be loaded for the selected range."
    />

    <template v-else-if="summary && viewModel">
      <DashboardKpiSection :cards="cards" :is-loading="false" :has-error="false" />

      <div class="grid gap-4 xl:grid-cols-2">
        <MiniDistributionChart
          chart-id="returns-by-outcome"
          title="Returns by Outcome"
          description="Returned versus unpaid outcomes during the selected range."
          :points="viewModel.chartPoints"
        />

        <Card class="dashboard-card-interactive">
          <CardHeader>
            <CardTitle>Restock rate</CardTitle>
            <CardDescription>
              Track how much returned quantity can flow back into available stock.
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
              <div class="rounded-md border p-3">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">Restock rate</p>
                <p class="mt-1 text-lg font-semibold">{{ viewModel.restockRate.toFixed(1) }}%</p>
              </div>
              <div class="rounded-md border p-3">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">Write-off rate</p>
                <p class="mt-1 text-lg font-semibold">{{ viewModel.writeOffRate.toFixed(1) }}%</p>
              </div>
              <div class="rounded-md border p-3">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">Status rows</p>
                <p class="mt-1 text-lg font-semibold">
                  {{ formatNumber(viewModel.statusBreakdown.length) }}
                </p>
              </div>
            </div>

            <div
              v-if="summary.total_returns === 0"
              class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
            >
              No returns recorded for the selected range.
            </div>

            <div v-else class="space-y-2 text-sm">
              <div
                v-for="status in viewModel.statusBreakdown"
                :key="status.label"
                class="flex items-center justify-between gap-3 border-b pb-2 last:border-b-0 last:pb-0"
              >
                <span class="capitalize">{{ status.label.replace(/_/g, ' ') }}</span>
                <span class="font-medium">{{ status.value }}</span>
              </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
              <Button v-if="canOpenReturnsWorkspace" as-child variant="outline">
                <RouterLink to="/returns">Open Returns Workspace</RouterLink>
              </Button>
              <Button v-if="canOpenInventoryWorkspace" as-child>
                <RouterLink to="/inventory/stocks">Open Inventory Workspace</RouterLink>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </template>
  </section>
</template>
