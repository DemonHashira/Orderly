<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'

defineProps<{
  show: boolean
}>()
</script>

<template>
  <Transition name="dashboard-section">
    <div
      v-if="show"
      class="bg-background/55 absolute inset-0 z-20 flex items-start justify-end rounded-xl p-3 backdrop-blur-[1px]"
      aria-live="polite"
      aria-busy="true"
    >
      <div
        class="bg-card text-muted-foreground inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs shadow-sm"
      >
        <Loader2 class="size-3.5 animate-spin" />
        Updating...
      </div>
    </div>
  </Transition>
</template>
