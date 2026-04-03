<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed, ref } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import OverlayScrollViewport from '@/components/ui/overlay-scroll/OverlayScrollViewport.vue'
import { Input } from '@/components/ui/input'
import { Popover, PopoverAnchor, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

export type SearchComboboxOption = {
  key: string
  label: string
}

const props = withDefaults(
  defineProps<{
    inputValue: string
    options: SearchComboboxOption[]
    selectedKey?: string
    customOption?: SearchComboboxOption | null
    placeholder?: string
    emptyMessage?: string
    clearLabel?: string
    loading?: boolean
    disabled?: boolean
    name?: string
    inputId?: string
    dataTest?: string
    inputClass?: HTMLAttributes['class']
    popoverClass?: HTMLAttributes['class']
    popoverSideOffset?: number
    autocomplete?: string
    spellcheck?: boolean
    ariaLabel?: string
    optionClass?: HTMLAttributes['class']
    maxListHeightClass?: HTMLAttributes['class']
  }>(),
  {
    selectedKey: '',
    customOption: null,
    placeholder: 'Search',
    emptyMessage: 'No options found.',
    clearLabel: 'Clear',
    loading: false,
    disabled: false,
    name: undefined,
    inputId: undefined,
    dataTest: undefined,
    inputClass: undefined,
    popoverClass: undefined,
    popoverSideOffset: 8,
    autocomplete: 'off',
    spellcheck: false,
    ariaLabel: undefined,
    optionClass: 'h-9',
    maxListHeightClass: 'max-h-64',
  },
)

const emit = defineEmits<{
  (e: 'update:inputValue', value: string): void
  (e: 'select', option: SearchComboboxOption): void
  (e: 'clear'): void
}>()

const isOpen = ref(false)

const resolvedPlaceholder = computed(() => props.placeholder ?? 'Search')
const normalizedInputValue = computed(() => props.inputValue.trim().toLowerCase())
const toggleDataTest = computed(() => (props.dataTest ? `${props.dataTest}-toggle` : undefined))
const triggerAriaLabel = computed(() => `Open ${resolvedPlaceholder.value.toLowerCase()} options`)

const filteredOptions = computed(() => {
  if (normalizedInputValue.value.length === 0) {
    return props.options
  }

  return props.options.filter((option) =>
    option.label.toLowerCase().includes(normalizedInputValue.value),
  )
})

const shouldShowLoadingState = computed(() => props.loading && filteredOptions.value.length === 0)

const onInput = (value: string | number) => {
  emit('update:inputValue', String(value))
  isOpen.value = true
}

const openOptions = () => {
  if (!props.disabled) {
    isOpen.value = true
  }
}

const selectOption = (option: SearchComboboxOption) => {
  emit('select', option)
  isOpen.value = false
}

const clearSelection = () => {
  emit('clear')
  isOpen.value = false
}

const getOptionClasses = (option: SearchComboboxOption) => {
  const isSelected = option.key === props.selectedKey

  return [
    'hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground relative flex w-full cursor-pointer items-center rounded-sm px-2 pr-6 text-left text-sm transition-colors',
    props.optionClass,
    isSelected ? 'bg-accent text-accent-foreground' : '',
  ]
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
          :model-value="inputValue"
          :placeholder="resolvedPlaceholder"
          :data-test="dataTest"
          :aria-label="ariaLabel"
          :autocomplete="autocomplete"
          :spellcheck="spellcheck"
          :class="['pr-10', inputClass]"
          @focus="openOptions"
          @update:model-value="onInput"
        />

        <PopoverTrigger as-child>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="disabled"
            :data-test="toggleDataTest"
            :aria-label="triggerAriaLabel"
            class="absolute right-1 top-1/2 z-10 size-7 -translate-y-1/2 rounded-sm"
          >
            <ChevronDown class="size-4" />
          </Button>
        </PopoverTrigger>
      </div>
    </PopoverAnchor>

    <PopoverContent
      side="bottom"
      align="start"
      :side-offset="popoverSideOffset"
      :class="popoverClass"
      :body-lock="false"
    >
      <div class="space-y-1">
        <OverlayScrollViewport :class="maxListHeightClass">
          <div v-if="shouldShowLoadingState" class="text-muted-foreground px-2 py-3 text-sm">
            Loading options…
          </div>

          <template v-else>
            <button
              v-for="option in filteredOptions"
              :key="option.key"
              type="button"
              :class="getOptionClasses(option)"
              @click="selectOption(option)"
            >
              <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
              <span
                v-if="option.key === selectedKey"
                class="absolute right-2 flex size-4 items-center justify-center"
              >
                <Check class="size-4" />
              </span>
            </button>

            <button
              v-if="customOption"
              :key="customOption.key"
              type="button"
              :class="getOptionClasses(customOption)"
              @click="selectOption(customOption)"
            >
              <span class="min-w-0 flex-1 truncate">{{ customOption.label }}</span>
              <span
                v-if="customOption.key === selectedKey"
                class="absolute right-2 flex size-4 items-center justify-center"
              >
                <Check class="size-4" />
              </span>
            </button>

            <div
              v-if="filteredOptions.length === 0 && !customOption"
              class="text-muted-foreground px-2 py-3 text-sm"
            >
              {{ emptyMessage }}
            </div>
          </template>
        </OverlayScrollViewport>

        <div class="border-t pt-1">
          <button
            type="button"
            class="hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground flex h-9 w-full cursor-pointer items-center rounded-sm px-2 text-left text-sm transition-colors"
            @click="clearSelection"
          >
            {{ clearLabel }}
          </button>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
