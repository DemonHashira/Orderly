<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { nextTick, onBeforeUnmount, onMounted, ref, watchPostEffect } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const viewportEl = ref<HTMLElement | null>(null)
const hasOverflow = ref(false)
const thumbHeight = ref(0)
const thumbOffset = ref(0)
const thumbVisible = ref(false)

let hideTimer: number | undefined
let resizeObserver: ResizeObserver | undefined

const clearHideTimer = () => {
  if (hideTimer !== undefined) {
    window.clearTimeout(hideTimer)
    hideTimer = undefined
  }
}

const syncMetrics = () => {
  const element = viewportEl.value
  if (!element) {
    hasOverflow.value = false
    return
  }

  const { clientHeight, scrollHeight, scrollTop } = element
  const overflow = scrollHeight > clientHeight + 1
  hasOverflow.value = overflow

  if (!overflow) {
    thumbHeight.value = 0
    thumbOffset.value = 0
    thumbVisible.value = false
    return
  }

  const minThumbHeight = 28
  const visibleRatio = clientHeight / scrollHeight
  const nextThumbHeight = Math.max(minThumbHeight, clientHeight * visibleRatio)
  const scrollRange = scrollHeight - clientHeight
  const travelRange = clientHeight - nextThumbHeight

  thumbHeight.value = nextThumbHeight
  thumbOffset.value =
    scrollRange > 0 ? Math.min(travelRange, (scrollTop / scrollRange) * travelRange) : 0
}

const revealThumb = () => {
  if (!hasOverflow.value) {
    return
  }

  thumbVisible.value = true
  clearHideTimer()
  hideTimer = window.setTimeout(() => {
    thumbVisible.value = false
  }, 700)
}

const handleScroll = () => {
  syncMetrics()
  revealThumb()
}

onMounted(() => {
  void nextTick(syncMetrics)

  if (viewportEl.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => {
      syncMetrics()
    })
    resizeObserver.observe(viewportEl.value)
  }

  window.addEventListener('resize', syncMetrics)
})

onBeforeUnmount(() => {
  clearHideTimer()
  resizeObserver?.disconnect()
  window.removeEventListener('resize', syncMetrics)
})

watchPostEffect(() => {
  void nextTick(syncMetrics)
})
</script>

<template>
  <div class="relative">
    <div
      ref="viewportEl"
      :class="
        cn(
          'overscroll-contain overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
          props.class,
        )
      "
      @scroll="handleScroll"
    >
      <slot />
    </div>

    <div
      v-if="hasOverflow"
      aria-hidden="true"
      class="pointer-events-none absolute inset-y-1 right-1 w-1.5"
    >
      <div
        class="absolute right-0 w-1.5 rounded-full bg-foreground/20 transition-opacity duration-200"
        :class="thumbVisible ? 'opacity-100' : 'opacity-0'"
        :style="{
          height: `${thumbHeight}px`,
          transform: `translateY(${thumbOffset}px)`,
        }"
      />
    </div>
  </div>
</template>
