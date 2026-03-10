<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverAnchor, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

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

const isOpen = ref(false)

const normalizedOptions = computed(() => [
  ...new Set(props.options.map((option) => option.trim()).filter((option) => option.length > 0)),
])

const toggleDataTest = computed(() => (props.dataTest ? `${props.dataTest}-toggle` : undefined))

const normalizedModelValue = computed(() => props.modelValue.trim().toLowerCase())
const hasCustomValue = computed(
  () =>
    normalizedModelValue.value.length > 0 &&
    !normalizedOptions.value.some((option) => option.toLowerCase() === normalizedModelValue.value),
)
const getOptionClasses = (option: string) => {
  const isSelected = option.toLowerCase() === normalizedModelValue.value
  return [
    'relative flex h-9 w-full cursor-pointer items-center rounded-sm px-2 pr-8 text-left text-sm transition-colors',
    'hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground',
    isSelected ? 'bg-accent text-accent-foreground' : '',
  ]
}

const onInput = (value: string | number) => {
  emit('update:modelValue', String(value))
}

const selectOption = (option: string) => {
  emit('update:modelValue', option)
  isOpen.value = false
}

const clearValue = () => {
  emit('update:modelValue', '')
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
          :autocomplete="autocomplete"
          :spellcheck="spellcheck"
          :model-value="modelValue"
          :placeholder="placeholder"
          :aria-label="ariaLabel"
          :data-test="dataTest"
          :class="['pr-10', inputClass]"
          @update:model-value="onInput"
        />

        <PopoverTrigger as-child>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            class="absolute right-1 top-1/2 z-10 size-7 -translate-y-1/2 rounded-sm"
            :aria-label="`Open ${placeholder.toLowerCase()} options`"
            :data-test="toggleDataTest"
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
      :class="['w-56 p-1', popoverClass]"
      :body-lock="false"
    >
      <div class="space-y-1">
        <button
          v-for="option in normalizedOptions"
          :key="option"
          type="button"
          :class="getOptionClasses(option)"
          @click="selectOption(option)"
        >
          <span class="truncate">{{ option }}</span>
          <span
            v-if="option.toLowerCase() === normalizedModelValue"
            class="absolute right-2 flex size-4 items-center justify-center"
          >
            <Check class="size-4" />
          </span>
        </button>

        <button
          v-if="hasCustomValue"
          type="button"
          :class="getOptionClasses(modelValue.trim())"
          @click="selectOption(modelValue.trim())"
        >
          <span class="truncate">Use "{{ modelValue.trim() }}"</span>
          <span class="absolute right-2 flex size-4 items-center justify-center">
            <Check class="size-4" />
          </span>
        </button>

        <div class="border-t pt-1">
          <button
            type="button"
            class="relative flex h-9 w-full cursor-pointer items-center rounded-sm px-2 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground"
            @click="clearValue"
          >
            Clear
          </button>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
