<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'

const props = defineProps<{
  status: string
}>()

const variant = computed(() => {
  const status = props.status.toLowerCase()

  if (['delivered', 'confirmed', 'restocked'].includes(status)) return 'default'
  if (['cancelled', 'returned', 'unpaid', 'damage'].includes(status)) return 'destructive'
  if (['ready_to_ship', 'shipped', 'restock'].includes(status)) return 'secondary'
  return 'outline'
})

const formattedLabel = computed(() => props.status.replace(/_/g, ' '))
</script>

<template>
  <Badge :variant="variant" class="capitalize">{{ formattedLabel }}</Badge>
</template>
