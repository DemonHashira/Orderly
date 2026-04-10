<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { isElementOverflowing } from './overflow-tooltip'

const props = withDefaults(
  defineProps<{
    text: string
    triggerClass?: string
    contentClass?: string
    dataTest?: string
  }>(),
  {
    triggerClass: '',
    contentClass: '',
    dataTest: undefined,
  },
)

const textElement = ref<HTMLElement | null>(null)
const isOverflowing = ref(false)

let resizeObserver: ResizeObserver | undefined

const measureOverflow = () => {
  if (!textElement.value) {
    isOverflowing.value = false
    return
  }

  isOverflowing.value = isElementOverflowing(textElement.value)
}

const bindResizeObserver = () => {
  resizeObserver?.disconnect()
  resizeObserver = undefined

  if (!textElement.value || typeof ResizeObserver === 'undefined') {
    return
  }

  resizeObserver = new ResizeObserver(() => {
    measureOverflow()
  })

  resizeObserver.observe(textElement.value)
}

const syncOverflowState = async () => {
  await nextTick()
  measureOverflow()
  bindResizeObserver()
}

onMounted(() => {
  void syncOverflowState()
  window.addEventListener('resize', measureOverflow)
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  window.removeEventListener('resize', measureOverflow)
})

watch(
  () => props.text,
  () => {
    void syncOverflowState()
  },
)
</script>

<template>
  <Tooltip>
    <TooltipTrigger as-child>
      <p :data-test="dataTest" ref="textElement" :class="cn('truncate', triggerClass)">
        {{ text }}
      </p>
    </TooltipTrigger>
    <TooltipContent v-if="isOverflowing" :class="contentClass">
      <span
        class="inline-block max-w-[min(20rem,var(--reka-tooltip-content-available-width))] whitespace-normal text-pretty"
      >
        {{ text }}
      </span>
    </TooltipContent>
  </Tooltip>
</template>
