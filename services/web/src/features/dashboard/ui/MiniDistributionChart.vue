<script setup lang="ts">
import { computed, type CSSProperties } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { DashboardChartBlockId } from '@/features/dashboard/model'

const props = defineProps<{
  title: string
  description: string
  chartId?: DashboardChartBlockId
  points: Array<{ label: string; value: number }>
}>()

const total = computed(() => props.points.reduce((sum, point) => sum + point.value, 0))

const normalizeLabel = (label: string) =>
  label
    .trim()
    .toLowerCase()
    .replace(/[\s/-]+/g, '_')

const getSemanticColor = (label: string, index: number) => {
  const key = normalizeLabel(label)

  if (props.chartId === 'orders-by-status') {
    const statusColorMap: Record<string, string> = {
      delivered: 'var(--chart-2)',
      shipped: 'var(--chart-3)',
      confirmed: 'var(--chart-1)',
      ready_to_ship: 'var(--chart-4)',
      draft: 'var(--chart-5)',
      cancelled: 'var(--destructive)',
      returned: 'var(--chart-2)',
      unpaid: 'var(--chart-5)',
    }

    return statusColorMap[key] ?? `var(--chart-${(index % 5) + 1})`
  }

  if (props.chartId === 'returns-by-outcome') {
    const returnsColorMap: Record<string, string> = {
      returned: 'var(--destructive)',
      unpaid: 'var(--chart-4)',
    }

    return returnsColorMap[key] ?? `var(--chart-${(index % 5) + 1})`
  }

  if (props.chartId === 'inventory-flow') {
    const inventoryColorMap: Record<string, string> = {
      in: 'var(--chart-2)',
      out: 'var(--destructive)',
    }

    return inventoryColorMap[key] ?? `var(--chart-${(index % 5) + 1})`
  }

  return `var(--chart-${(index % 5) + 1})`
}

const getBarStyle = (label: string, index: number, value: number): CSSProperties => ({
  width: `${total.value > 0 ? Math.max(4, (value / total.value) * 100) : 0}%`,
  backgroundColor: getSemanticColor(label, index),
})
</script>

<template>
  <Card :class="['dashboard-card-interactive', { 'self-start': points.length === 0 }]">
    <CardHeader>
      <CardTitle>{{ title }}</CardTitle>
      <CardDescription>{{ description }}</CardDescription>
    </CardHeader>

    <div v-if="points.length === 0" class="px-6 pb-6">
      <p class="font-medium">No data</p>
      <p class="text-muted-foreground mt-1">No chartable values for the selected range.</p>
    </div>

    <CardContent v-else>
      <div class="space-y-4">
        <div v-for="(point, index) in points" :key="point.label" class="space-y-1">
          <div class="flex items-center justify-between text-sm">
            <span class="capitalize">{{ point.label }}</span>
            <span class="text-muted-foreground">{{ point.value }}</span>
          </div>
          <div class="bg-muted h-2 w-full overflow-hidden rounded-full">
            <div
              class="h-full rounded-full transition-all"
              :style="getBarStyle(point.label, index, point.value)"
            />
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
