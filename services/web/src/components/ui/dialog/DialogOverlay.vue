<script setup lang="ts">
import { computed } from 'vue'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { injectDialogRootContext } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
const delegatedProps = reactiveOmit(props, 'class')
const dialogRootContext = injectDialogRootContext(null)
const isOpen = computed(() => Boolean(dialogRootContext?.open.value))
</script>

<template>
  <Transition
    enter-active-class="duration-200 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="duration-150 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isOpen"
      data-slot="dialog-overlay"
      v-bind="delegatedProps"
      :class="cn('fixed inset-0 z-50 bg-black/70', props.class)"
    />
  </Transition>
</template>
