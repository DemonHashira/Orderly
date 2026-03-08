<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { DashboardChartBlockId } from '@/features/dashboard/model'
import type { ChartCard } from '@/features/dashboard/types'
import { ApiErrorAlert, EmptyStateCard } from '@/shared/ui'
import MiniDistributionChart from './MiniDistributionChart.vue'

const props = defineProps<{
  charts: ChartCard[]
  hasError: boolean
}>()
const slots = useSlots()

const chartById = computed(() => {
  const map = new Map<DashboardChartBlockId, ChartCard>()
  props.charts.forEach((chart) => {
    map.set(chart.id, chart)
  })
  return map
})

const hasThreeChartLayout = computed(
  () =>
    chartById.value.has('orders-by-status') &&
    chartById.value.has('returns-by-outcome') &&
    chartById.value.has('inventory-flow') &&
    props.charts.length === 3,
)

const hasOrdersAndReturnsPair = computed(
  () =>
    chartById.value.has('orders-by-status') &&
    chartById.value.has('returns-by-outcome') &&
    props.charts.length === 2,
)

const hasCompanion = computed(() => Boolean(slots.companion))

const gridClass = computed(() => {
  if (props.charts.length >= 3) return 'grid items-start gap-4 xl:grid-cols-3'
  if (props.charts.length === 2) return 'grid items-start gap-4 xl:grid-cols-2'
  return 'grid items-start gap-4 grid-cols-1'
})
</script>

<template>
  <div class="space-y-3">
    <ApiErrorAlert
      v-if="props.hasError"
      message="Chart summary data could not be loaded. The rest of the dashboard is still usable."
    />

    <Transition mode="out-in" name="dashboard-section">
      <div v-if="props.charts.length > 0" key="charts-ready">
        <div
          v-if="hasOrdersAndReturnsPair && hasCompanion"
          class="grid items-start gap-4 xl:grid-cols-2"
        >
          <div class="h-full [&>*]:h-full">
            <MiniDistributionChart
              :chart-id="'orders-by-status'"
              :title="chartById.get('orders-by-status')!.title"
              :description="chartById.get('orders-by-status')!.description"
              :points="chartById.get('orders-by-status')!.points"
            />
          </div>

          <div class="grid h-full grid-rows-2 gap-4">
            <div class="h-full [&>*]:h-full">
              <MiniDistributionChart
                :chart-id="'returns-by-outcome'"
                :title="chartById.get('returns-by-outcome')!.title"
                :description="chartById.get('returns-by-outcome')!.description"
                :points="chartById.get('returns-by-outcome')!.points"
              />
            </div>
            <div class="h-full [&>*]:h-full">
              <slot name="companion" />
            </div>
          </div>
        </div>

        <div v-else-if="hasThreeChartLayout" class="grid gap-4 xl:grid-cols-2">
          <div class="h-full [&>*]:h-full">
            <MiniDistributionChart
              :chart-id="'orders-by-status'"
              :title="chartById.get('orders-by-status')!.title"
              :description="chartById.get('orders-by-status')!.description"
              :points="chartById.get('orders-by-status')!.points"
            />
          </div>

          <div class="grid h-full grid-rows-2 gap-4">
            <div class="h-full [&>*]:h-full">
              <MiniDistributionChart
                :chart-id="'returns-by-outcome'"
                :title="chartById.get('returns-by-outcome')!.title"
                :description="chartById.get('returns-by-outcome')!.description"
                :points="chartById.get('returns-by-outcome')!.points"
              />
            </div>
            <div class="h-full [&>*]:h-full">
              <MiniDistributionChart
                :chart-id="'inventory-flow'"
                :title="chartById.get('inventory-flow')!.title"
                :description="chartById.get('inventory-flow')!.description"
                :points="chartById.get('inventory-flow')!.points"
              />
            </div>
          </div>
        </div>

        <TransitionGroup v-else name="dashboard-grid" tag="div" :class="gridClass">
          <MiniDistributionChart
            v-for="chart in props.charts"
            :key="chart.id"
            :chart-id="chart.id"
            :title="chart.title"
            :description="chart.description"
            :points="chart.points"
          />
        </TransitionGroup>
      </div>

      <EmptyStateCard
        v-else
        key="charts-empty"
        title="No chart data"
        description="No chartable dashboard summaries are available for your role."
      />
    </Transition>
  </div>
</template>
