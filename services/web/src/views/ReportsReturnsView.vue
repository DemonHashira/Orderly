<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/features/auth/composables/useAuth'
import type { DashboardKpiCard } from '@/features/dashboard/types'
import DashboardKpiSection from '@/features/dashboard/ui/DashboardKpiSection.vue'
import MiniDistributionChart from '@/features/dashboard/ui/MiniDistributionChart.vue'
import { useReportDateRangeQuery } from '@/features/reports/composables/useReportDateRangeQuery'
import { useReturnsReportSummaryQuery } from '@/features/reports/composables/useReturnsReportSummaryQuery'
import { buildReturnsReportViewModel } from '@/features/reports/model/report-view-models'
import type { ReportActionLink } from '@/features/reports/model/report-types'
import ReportActionLinksPanel from '@/features/reports/ui/ReportActionLinksPanel.vue'
import ReportBreakdownTable from '@/features/reports/ui/ReportBreakdownTable.vue'
import ReportComparisonPanel from '@/features/reports/ui/ReportComparisonPanel.vue'
import ReportExceptionsTable from '@/features/reports/ui/ReportExceptionsTable.vue'
import ReportSurfaceTabs from '@/features/reports/ui/ReportSurfaceTabs.vue'
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
const visibleActions = computed<ReportActionLink[]>(() => {
  if (!viewModel.value) {
    return []
  }

  const actions = permissions.value.includes('returns.view') ? [...viewModel.value.actionLinks] : []

  if (permissions.value.includes('inventory.view')) {
    actions.push({
      id: 'open-inventory-workspace',
      label: 'Open Inventory Workspace',
      description: 'Review stock rows that will be affected by restocked returns.',
      to: {
        path: '/inventory/stocks',
        query: {},
      },
    })
  }

  return actions
})
const isInitialLoading = computed(() => reportQuery.isLoading.value && summary.value == null)
const isRefetching = computed(() => !isInitialLoading.value && reportQuery.isFetching.value)
const errorMessage = computed(() =>
  reportQuery.error.value ? normalizeApiError(reportQuery.error.value).message : '',
)
const showErrorState = computed(() => Boolean(reportQuery.error.value) && summary.value == null)
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

  <section v-else class="relative flex flex-col gap-6">
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

      <ReportSurfaceTabs>
        <template #overview>
          <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <MiniDistributionChart
              chart-id="returns-by-outcome"
              title="Returns by Outcome"
              description="Returned versus unpaid outcomes during the selected range."
              :points="viewModel.chartPoints"
            />

            <ReportComparisonPanel
              :metrics="viewModel.comparisonMetrics"
              :range-label="viewModel.comparisonRangeLabel"
            />
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <Card
              v-for="item in viewModel.overviewCards"
              :key="item.id"
              class="dashboard-card-interactive"
            >
              <CardHeader class="pb-3">
                <CardTitle class="text-base">{{ item.label }}</CardTitle>
                <CardDescription>{{ item.description }}</CardDescription>
              </CardHeader>
              <CardContent>
                <p class="text-2xl font-semibold">{{ item.value }}</p>
              </CardContent>
            </Card>
          </div>

          <Card v-if="viewModel.zeroStateMessage" class="dashboard-card-interactive">
            <CardContent class="py-6 text-sm text-muted-foreground">
              {{ viewModel.zeroStateMessage }}
            </CardContent>
          </Card>

          <ReportActionLinksPanel :actions="visibleActions" />
        </template>

        <template #exceptions>
          <ReportExceptionsTable
            v-for="section in viewModel.exceptionSections"
            :key="section.id"
            :section="section"
          />
        </template>

        <template #breakdowns>
          <ReportBreakdownTable
            v-for="section in viewModel.breakdownSections"
            :key="section.id"
            :section="section"
          />
        </template>
      </ReportSurfaceTabs>
    </template>
  </section>
</template>
