<script setup lang="ts">
import { Button } from '@/components/ui/button'

defineProps<{
  currentPage: number
  totalPages: number
}>()

const emit = defineEmits<{
  (e: 'update:page', value: number): void
}>()
</script>

<template>
  <div class="flex items-center justify-between gap-3">
    <p class="text-muted-foreground text-sm">Page {{ currentPage }} of {{ totalPages }}</p>
    <div class="flex items-center gap-2">
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage <= 1"
        @click="emit('update:page', Math.max(1, currentPage - 1))"
      >
        Previous
      </Button>
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage >= totalPages"
        @click="emit('update:page', Math.min(totalPages, currentPage + 1))"
      >
        Next
      </Button>
    </div>
  </div>
</template>
