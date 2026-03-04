<script setup lang="ts">
import { ref, watch } from 'vue'
import { Input } from '@/components/ui/input'

const props = withDefaults(
  defineProps<{
    modelValue?: string
    placeholder?: string
    delay?: number
  }>(),
  {
    modelValue: '',
    placeholder: 'Search…',
    delay: 300,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const localValue = ref(props.modelValue)
let timeoutId: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.modelValue,
  (value) => {
    localValue.value = value
  },
)

watch(localValue, (value) => {
  if (timeoutId) {
    clearTimeout(timeoutId)
  }

  timeoutId = setTimeout(() => {
    emit('update:modelValue', value)
  }, props.delay)
})
</script>

<template>
  <Input v-model="localValue" :placeholder="placeholder" />
</template>
