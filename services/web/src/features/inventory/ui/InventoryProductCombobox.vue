<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverAnchor, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
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

const isOpen = ref(false)

const normalizedSelectedLabel = computed(() => props.selectedLabel.trim())
const toggleDataTest = computed(() => (props.dataTest ? `${props.dataTest}-toggle` : undefined))

const onInput = (value: string | number) => {
  const nextValue = String(value)

  if (
    props.modelValue &&
    normalizedSelectedLabel.value.length > 0 &&
    nextValue.trim() !== normalizedSelectedLabel.value
  ) {
    emit('update:modelValue', '')
  }

  emit('update:searchValue', nextValue)
}

const onSelect = (option: InventoryProductOption) => {
  emit('update:modelValue', String(option.id))
  emit('update:searchValue', option.label)
  isOpen.value = false
}

const clearSelection = () => {
  emit('update:modelValue', '')
  emit('update:searchValue', '')
  isOpen.value = false
}
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverAnchor as-child>
      <div class="relative min-w-0">
        <Input
          :id="inputId"
          :name="name"
          :disabled="disabled"
          :model-value="searchValue"
          :placeholder="placeholder"
          :data-test="dataTest"
          class="pr-10"
          autocomplete="off"
          spellcheck="false"
          @update:model-value="onInput"
        />

        <PopoverTrigger as-child>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="disabled"
            :data-test="toggleDataTest"
            :aria-label="`Open ${placeholder.toLowerCase()} options`"
            class="absolute right-1 top-1/2 z-10 size-7 -translate-y-1/2 rounded-sm"
          >
            <ChevronDown class="size-4" />
          </Button>
        </PopoverTrigger>
      </div>
    </PopoverAnchor>

    <PopoverContent side="bottom" align="start" :body-lock="false" class="w-80 p-1">
      <div class="flex flex-col gap-1">
        <div v-if="loading" class="text-muted-foreground px-2 py-3 text-sm">Loading products…</div>
        <template v-else-if="options.length > 0">
          <button
            v-for="option in options"
            :key="option.id"
            type="button"
            class="hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground relative flex h-10 w-full items-center rounded-sm px-2 pr-8 text-left text-sm transition-colors"
            @click="onSelect(option)"
          >
            <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
            <span
              v-if="String(option.id) === modelValue"
              class="absolute right-2 flex size-4 items-center justify-center"
            >
              <Check class="size-4" />
            </span>
          </button>
        </template>
        <div v-else class="text-muted-foreground px-2 py-3 text-sm">{{ emptyMessage }}</div>

        <div class="border-t pt-1">
          <button
            type="button"
            class="hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground flex h-9 w-full items-center rounded-sm px-2 text-left text-sm transition-colors"
            @click="clearSelection"
          >
            Clear
          </button>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
