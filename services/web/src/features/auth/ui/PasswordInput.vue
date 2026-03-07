<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed, ref } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<{
  modelValue?: string | number
  defaultValue?: string | number
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void
}>()

const isVisible = ref(false)
const inputType = computed(() => (isVisible.value ? 'text' : 'password'))

const modelValueProxy = computed({
  get: () => props.modelValue ?? props.defaultValue ?? '',
  set: (value: string | number) => emits('update:modelValue', value),
})

const toggleVisibility = () => {
  isVisible.value = !isVisible.value
}
</script>

<template>
  <div class="relative">
    <Input
      v-model="modelValueProxy"
      v-bind="$attrs"
      :type="inputType"
      :class="cn('pr-10', props.class)"
    />
    <button
      type="button"
      class="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center transition-colors"
      :aria-label="isVisible ? 'Hide password' : 'Show password'"
      @click="toggleVisibility"
    >
      <EyeOff v-if="isVisible" class="size-4" />
      <Eye v-else class="size-4" />
    </button>
  </div>
</template>
