<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

defineProps<{
  title: string
  description: string
}>()

const slots = useSlots()
const hasDefaultSlot = computed(() => Boolean(slots.default))
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>{{ title }}</CardTitle>
      <CardDescription>{{ description }}</CardDescription>
    </CardHeader>
    <CardContent v-if="hasDefaultSlot">
      <slot />
    </CardContent>
  </Card>
</template>
