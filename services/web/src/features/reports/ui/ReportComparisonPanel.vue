<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { ReportComparisonMetricViewModel } from '@/features/reports/model/report-types'

const props = defineProps<{
  metrics: ReportComparisonMetricViewModel[]
  rangeLabel?: string | null
}>()

const toneByDirection = computed<
  Record<ReportComparisonMetricViewModel['direction'], 'default' | 'secondary' | 'outline'>
>(() => ({
  up: 'default',
  down: 'outline',
  flat: 'secondary',
}))

const directionSummary = computed(() => {
  const up = props.metrics.filter((metric) => metric.direction === 'up').length
  const down = props.metrics.filter((metric) => metric.direction === 'down').length
  const flat = props.metrics.filter((metric) => metric.direction === 'flat').length

  return [
    {
      id: 'up',
      label: 'Up',
      value: String(up),
      description: 'Comparison metrics above the previous period.',
    },
    {
      id: 'down',
      label: 'Down',
      value: String(down),
      description: 'Comparison metrics below the previous period.',
    },
    {
      id: 'flat',
      label: 'Unchanged',
      value: String(flat),
      description: 'Comparison metrics unchanged period over period.',
    },
  ]
})
</script>

<template>
  <Card class="dashboard-card-interactive">
    <CardHeader>
      <CardTitle>Compare to previous period</CardTitle>
      <CardDescription>
        <template v-if="rangeLabel">Previous range: {{ rangeLabel }}</template>
        <template v-else>Comparison becomes available when both range dates are selected.</template>
      </CardDescription>
    </CardHeader>
    <CardContent class="space-y-5">
      <div v-if="metrics.length > 0" class="space-y-4">
        <div class="grid gap-3 xl:grid-cols-3">
          <article
            v-for="metric in metrics"
            :key="metric.id"
            class="rounded-lg border bg-muted/20 p-4"
          >
            <div class="flex h-full flex-col gap-3">
              <div class="space-y-2">
                <p class="text-muted-foreground text-xs uppercase tracking-wide">
                  {{ metric.label }}
                </p>
                <Badge
                  :variant="toneByDirection[metric.direction]"
                  class="inline-flex max-w-full text-xs sm:text-sm"
                >
                  {{ metric.deltaValue }}
                </Badge>
                <p class="break-words text-2xl font-semibold sm:text-3xl">
                  {{ metric.currentValue }}
                </p>
              </div>

              <Separator />

              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-muted-foreground">Previous</span>
                <span class="font-medium">{{ metric.previousValue }}</span>
              </div>
              <p v-if="metric.deltaPercentageLabel" class="text-muted-foreground text-xs">
                {{ metric.deltaPercentageLabel }} vs previous period
              </p>
            </div>
          </article>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <article
            v-for="entry in directionSummary"
            :key="entry.id"
            class="rounded-lg border border-dashed bg-muted/10 p-4"
          >
            <p class="text-muted-foreground text-xs uppercase tracking-wide">{{ entry.label }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ entry.value }}</p>
            <p class="text-muted-foreground mt-2 text-xs">
              {{ entry.description }}
            </p>
          </article>
        </div>
      </div>

      <p v-else class="text-muted-foreground text-sm">
        All-time reports do not have an adjacent prior range to compare against.
      </p>
    </CardContent>
  </Card>
</template>
