<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { ReportActionLink } from '@/features/reports/model/report-types'

const props = defineProps<{
  actions: ReportActionLink[]
}>()

const hasMultipleActions = computed(() => props.actions.length > 1)
const singleAction = computed<ReportActionLink | null>(() => props.actions[0] ?? null)
</script>

<template>
  <Card v-if="hasMultipleActions" class="dashboard-card-interactive">
    <CardHeader>
      <CardTitle>Recommended actions</CardTitle>
      <CardDescription>
        Use these drilldowns to move from summary metrics into the operational workspace.
      </CardDescription>
    </CardHeader>
    <CardContent class="space-y-3">
      <template v-if="actions.length > 0">
        <article
          v-for="(action, index) in actions"
          :key="action.id"
          class="rounded-lg border bg-muted/20 p-4"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
              <h3 class="font-medium">{{ action.label }}</h3>
              <p class="text-muted-foreground text-sm">{{ action.description }}</p>
            </div>
            <Button as-child :variant="index === 0 ? 'default' : 'outline'">
              <RouterLink :to="action.to">{{ action.label }}</RouterLink>
            </Button>
          </div>
        </article>
      </template>
      <p v-else class="text-muted-foreground text-sm">
        No linked workspaces are available for this report.
      </p>
    </CardContent>
  </Card>

  <Card v-else-if="singleAction" class="dashboard-card-interactive">
    <CardContent class="p-4">
      <article class="rounded-lg border bg-muted/20 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="space-y-1">
            <h3 class="font-medium">{{ singleAction.label }}</h3>
            <p class="text-muted-foreground text-sm">{{ singleAction.description }}</p>
          </div>
          <Button as-child>
            <RouterLink :to="singleAction.to">{{ singleAction.label }}</RouterLink>
          </Button>
        </div>
      </article>
    </CardContent>
  </Card>
</template>
