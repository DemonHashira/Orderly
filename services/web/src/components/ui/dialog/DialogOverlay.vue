<script setup lang="ts">
import type { DialogOverlayProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { DialogOverlay, injectDialogRootContext, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { useDocumentRootScrollLock } from './useDocumentRootScrollLock'

const props = defineProps<DialogOverlayProps & { class?: HTMLAttributes['class'] }>()
const delegatedProps = reactiveOmit(props, 'class')
const forwarded = useForwardProps(delegatedProps)
const dialogRootContext = injectDialogRootContext()

useDocumentRootScrollLock(dialogRootContext.open)
</script>

<template>
  <DialogOverlay
    data-slot="dialog-overlay"
    v-bind="forwarded"
    :class="
      cn(
        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 fixed inset-0 z-50 bg-black/70 duration-200',
        props.class,
      )
    "
  />
</template>
