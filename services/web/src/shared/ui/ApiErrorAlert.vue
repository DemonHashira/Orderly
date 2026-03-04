<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'

defineProps<{
  title?: string
  message: string
}>()
</script>

<template>
  <Alert variant="destructive">
    <AlertCircle class="size-4" />
    <AlertTitle>{{ title ?? 'Something went wrong' }}</AlertTitle>
    <AlertDescription>{{ message }}</AlertDescription>
  </Alert>
</template>
