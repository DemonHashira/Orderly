<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import type { KpiCard } from '@/features/dashboard/types'
import { ApiErrorAlert, EmptyStateCard } from '@/shared/ui'

const props = defineProps<{
  cards: KpiCard[]
  isLoading: boolean
  hasError: boolean
}>()

const gridClass = computed(() => {
  if (props.cards.length >= 4) return 'grid gap-4 md:grid-cols-2 xl:grid-cols-4'
  if (props.cards.length === 3) return 'grid gap-4 md:grid-cols-2 xl:grid-cols-3'
  if (props.cards.length === 2) return 'grid gap-4 md:grid-cols-2'
  return 'grid gap-4 grid-cols-1'
})
</script>

<template>
  <div class="space-y-3">
    <ApiErrorAlert
      v-if="props.hasError"
      message="KPI summary data could not be loaded. Other dashboard sections may still be available."
    />

    <Transition mode="out-in" name="dashboard-section">
      <div
        v-if="props.isLoading"
        key="kpi-loading"
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
      >
        <Card v-for="card in 4" :key="`kpi-skeleton-${card}`" class="h-32">
          <CardContent class="space-y-4 pt-6">
            <Skeleton class="h-4 w-24" />
            <Skeleton class="h-9 w-28" />
            <Skeleton class="h-3 w-40" />
          </CardContent>
        </Card>
      </div>

      <TransitionGroup
        v-else-if="props.cards.length > 0"
        key="kpi-ready"
        name="dashboard-grid"
        tag="div"
        :class="gridClass"
      >
        <Card v-for="card in props.cards" :key="card.id" class="dashboard-card-interactive">
          <CardHeader>
            <CardDescription>{{ card.title }}</CardDescription>
            <CardTitle class="text-3xl">{{ card.value }}</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-muted-foreground text-xs">{{ card.description }}</p>
          </CardContent>
        </Card>
      </TransitionGroup>

      <EmptyStateCard
        v-else
        key="kpi-empty"
        title="No KPI data"
        description="No summary metrics are available for your permissions and selected date range."
      />
    </Transition>
  </div>
</template>
