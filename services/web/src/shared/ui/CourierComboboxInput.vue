<script setup lang="ts">
import { computed } from 'vue'
import SearchCombobox from '@/shared/ui/SearchCombobox.vue'
import type { SearchComboboxOption } from '@/shared/ui/SearchCombobox.vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    options: readonly string[]
    placeholder?: string
    inputId?: string
    name?: string
    autocomplete?: string
    spellcheck?: boolean
    ariaLabel?: string
    dataTest?: string
    inputClass?: string
    popoverClass?: string
    popoverSideOffset?: number
  }>(),
  {
    placeholder: 'Courier',
    inputId: undefined,
    name: undefined,
    autocomplete: 'off',
    spellcheck: false,
    ariaLabel: undefined,
    dataTest: undefined,
    inputClass: undefined,
    popoverClass: undefined,
    popoverSideOffset: 8,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const normalizedOptions = computed(() => [
  ...new Set(props.options.map((option) => option.trim()).filter((option) => option.length > 0)),
])

const comboboxOptions = computed<SearchComboboxOption[]>(() =>
  normalizedOptions.value.map((option) => ({
    key: option,
    label: option,
  })),
)

const normalizedModelValue = computed(() => props.modelValue.trim().toLowerCase())

const customOption = computed<SearchComboboxOption | null>(() => {
  const trimmedValue = props.modelValue.trim()

  if (
    trimmedValue.length === 0 ||
    normalizedOptions.value.some((option) => option.toLowerCase() === normalizedModelValue.value)
  ) {
    return null
  }

  return {
    key: trimmedValue,
    label: `Use "${trimmedValue}"`,
  }
})

const handleSelect = (option: SearchComboboxOption) => {
  emit('update:modelValue', option.key)
}

const clearValue = () => {
  emit('update:modelValue', '')
}
</script>

<template>
  <SearchCombobox
    :input-value="modelValue"
    :selected-key="modelValue"
    :options="comboboxOptions"
    :custom-option="customOption"
    :placeholder="placeholder"
    :input-id="inputId"
    :name="name"
    :autocomplete="autocomplete"
    :spellcheck="spellcheck"
    :aria-label="ariaLabel"
    :data-test="dataTest"
    :input-class="inputClass"
    :popover-class="['w-56 p-1', popoverClass]"
    :popover-side-offset="popoverSideOffset"
    @update:input-value="$emit('update:modelValue', $event)"
    @select="handleSelect"
    @clear="clearValue"
  />
</template>
