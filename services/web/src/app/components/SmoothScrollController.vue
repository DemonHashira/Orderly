<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core'
import { VueLenis } from 'lenis/vue'
import { useSmoothScroll } from '@/app/composables/useSmoothScroll'

const prefersReducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)')
const lenisOptions = {
  allowNestedScroll: true,
}

useSmoothScroll()
</script>

<template>
  <VueLenis v-if="!prefersReducedMotion" root :auto-raf="true" :options="lenisOptions" />
</template>
