<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton'
</script>

<template>
  <section class="space-y-4">
    <div class="space-y-2">
      <Skeleton class="h-8 w-56" />
      <Skeleton class="h-4 w-96 max-w-full" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <Skeleton v-for="item in 4" :key="`kpi-skeleton-${item}`" class="h-32 rounded-xl" />
    </div>

    <Skeleton class="h-64 rounded-xl" />
    <Skeleton class="h-64 rounded-xl" />
  </section>
</template>
