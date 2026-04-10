<script setup lang="ts">
import SmoothScrollController from '@/app/components/SmoothScrollController.vue'
import { Toaster } from '@/components/ui/sonner'
</script>

<template>
  <SmoothScrollController />
  <RouterView />
  <Toaster position="top-right" close-button />
</template>
