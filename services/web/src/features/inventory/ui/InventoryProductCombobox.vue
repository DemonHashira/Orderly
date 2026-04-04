<script setup lang="ts">
import { computed } from 'vue'
import SearchCombobox from '@/shared/ui/SearchCombobox.vue'
import type { SearchComboboxOption } from '@/shared/ui/SearchCombobox.vue'
import type { InventoryProductOption } from '@/features/inventory/types'

const props = withDefaults(
  defineProps<{
    modelValue: string
    searchValue: string
    options: InventoryProductOption[]
    selectedLabel?: string
    placeholder?: string
    emptyMessage?: string
    loading?: boolean
    disabled?: boolean
    name?: string
    inputId?: string
    dataTest?: string
  }>(),
  {
    selectedLabel: '',
    placeholder: 'Search product',
    emptyMessage: 'No products found.',
    loading: false,
    disabled: false,
    name: undefined,
    inputId: undefined,
    dataTest: undefined,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'update:searchValue', value: string): void
}>()

const normalizedSelectedLabel = computed(() => props.selectedLabel.trim())
const comboboxOptions = computed<SearchComboboxOption[]>(() =>
  props.options.map((option) => ({
    key: String(option.id),
    label: option.label,
  })),
)

const handleInput = (value: string) => {
  if (
    props.modelValue &&
    normalizedSelectedLabel.value.length > 0 &&
    value.trim() !== normalizedSelectedLabel.value
  ) {
    emit('update:modelValue', '')
  }

  emit('update:searchValue', value)
}

const handleSelect = (option: SearchComboboxOption) => {
  emit('update:modelValue', option.key)
  emit('update:searchValue', option.label)
}

const clearSelection = () => {
  emit('update:modelValue', '')
  emit('update:searchValue', '')
}
</script>

<template>
  <SearchCombobox
    :input-value="searchValue"
    :selected-key="modelValue"
    :options="comboboxOptions"
    :placeholder="placeholder"
    :empty-message="emptyMessage"
    :loading="loading"
    :disabled="disabled"
    :name="name"
    :input-id="inputId"
    :data-test="dataTest"
    popover-class="w-80 p-1"
    option-class="h-10"
    @update:input-value="handleInput"
    @select="handleSelect"
    @clear="clearSelection"
  />
</template>
