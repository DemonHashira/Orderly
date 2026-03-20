<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/features/auth/composables/useAuth'
import type { DashboardKpiCard } from '@/features/dashboard/types'
import DashboardKpiSection from '@/features/dashboard/ui/DashboardKpiSection.vue'
import MiniDistributionChart from '@/features/dashboard/ui/MiniDistributionChart.vue'
import { useInventoryReportSummaryQuery } from '@/features/reports/composables/useInventoryReportSummaryQuery'
import { useReportDateRangeQuery } from '@/features/reports/composables/useReportDateRangeQuery'
import { buildInventoryReportViewModel } from '@/features/reports/model/report-view-models'
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
const reportQuery = useInventoryReportSummaryQuery(
  computed(() => ({
    from: range.from.value,
    to: range.to.value,
  })),
)

const summaryResponse = computed(() => reportQuery.data.value)
const summary = computed(() => summaryResponse.value?.data)
const generatedAt = computed(() => summaryResponse.value?.meta.generated_at)
const viewModel = computed(() =>
  summary.value ? buildInventoryReportViewModel(summary.value) : null,
)
const cards = computed<DashboardKpiCard[]>(() => viewModel.value?.cards ?? [])
const isInitialLoading = computed(() => reportQuery.isLoading.value && summary.value == null)
const isRefetching = computed(() => !isInitialLoading.value && reportQuery.isFetching.value)
const errorMessage = computed(() =>
  reportQuery.error.value ? normalizeApiError(reportQuery.error.value).message : '',
)
const showErrorState = computed(() => Boolean(reportQuery.error.value) && summary.value == null)
const canViewInventoryWorkspace = computed(() => permissions.value.includes('inventory.view'))
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
      title="Inventory Report"
      description="Inventory snapshot totals with movement activity for the selected range."
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
      title="Inventory report unavailable"
      description="The inventory summary could not be loaded for the selected range."
    />

    <template v-else-if="summary && viewModel">
      <DashboardKpiSection :cards="cards" :is-loading="false" :has-error="false" />

      <div class="grid gap-4 xl:grid-cols-2">
        <MiniDistributionChart
          chart-id="inventory-flow"
          title="Inventory Flow"
          description="Movement in versus movement out during the selected range."
          :points="viewModel.chartPoints"
        />

        <Card class="dashboard-card-interactive">
          <CardHeader>
            <CardTitle>Stock context</CardTitle>
            <CardDescription>
              Stock totals are current snapshot values, while movement counts are filtered by the
              selected date range.
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
              <div class="rounded-md border p-3">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">Reserved</p>
                <p class="mt-1 text-lg font-semibold">{{ formatNumber(summary.total_reserved) }}</p>
              </div>
              <div class="rounded-md border p-3">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">Net movement</p>
                <p class="mt-1 text-lg font-semibold">{{ formatNumber(viewModel.netMovement) }}</p>
              </div>
              <div class="rounded-md border p-3">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">Low stock</p>
                <p class="mt-1 text-lg font-semibold">
                  {{ formatNumber(summary.low_stock_count) }}
                </p>
              </div>
            </div>

            <div
              v-if="
                summary.total_skus === 0 &&
                summary.movement_in_qty === 0 &&
                summary.movement_out_qty === 0
              "
              class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
            >
              No inventory activity was recorded for the selected range.
            </div>

            <div v-if="canViewInventoryWorkspace" class="flex flex-wrap justify-end gap-2">
              <Button as-child variant="outline">
                <RouterLink to="/inventory/stocks">Open Inventory Stocks</RouterLink>
              </Button>
              <Button as-child>
                <RouterLink to="/inventory/movements">Open Inventory Movements</RouterLink>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </template>
  </section>
</template>
