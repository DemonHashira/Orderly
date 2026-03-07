<script setup lang="ts">
import { computed } from 'vue'
import { CalendarIcon } from 'lucide-vue-next'
import { parseDate } from '@internationalized/date'
import type { DateValue } from 'reka-ui'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

type Preset = 'all' | 'last_7' | 'last_30' | 'custom'
type SelectablePreset = Exclude<Preset, 'custom'>

const props = defineProps<{
  from?: string
  to?: string
}>()

const emit = defineEmits<{
  (e: 'update:from', value: string): void
  (e: 'update:to', value: string): void
  (e: 'preset', value: SelectablePreset): void
}>()

const formatDate = (date: Date): string => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const isPresetRange = (days: number): boolean => {
  if (!props.from || !props.to) {
    return false
  }

  const end = new Date()
  const start = new Date()
  start.setDate(end.getDate() - (days - 1))

  return props.from === formatDate(start) && props.to === formatDate(end)
}

const preset = computed<Preset>(() => {
  if (!props.from && !props.to) {
    return 'all'
  }

  if (isPresetRange(7)) {
    return 'last_7'
  }

  if (isPresetRange(30)) {
    return 'last_30'
  }

  return 'custom'
})

const selectablePreset = computed<SelectablePreset | undefined>(() => {
  if (preset.value === 'custom') {
    return undefined
  }

  return preset.value
})

const presetPlaceholder = computed(() =>
  preset.value === 'custom' ? 'Custom range' : 'Select range',
)

const fromDateValue = computed<DateValue | undefined>(() => {
  if (!props.from) {
    return undefined
  }

  try {
    return parseDate(props.from)
  } catch {
    return undefined
  }
})

const toDateValue = computed<DateValue | undefined>(() => {
  if (!props.to) {
    return undefined
  }

  try {
    return parseDate(props.to)
  } catch {
    return undefined
  }
})

const formatDisplayDate = (value?: string) => {
  if (!value) {
    return 'dd.mm.yyyy'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return 'dd.mm.yyyy'
  }

  return date.toLocaleDateString('en-GB')
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 md:flex-nowrap">
    <Select
      :model-value="selectablePreset"
      @update:model-value="
        (value) =>
          value === 'all' || value === 'last_7' || value === 'last_30'
            ? emit('preset', value)
            : undefined
      "
    >
      <SelectTrigger class="w-[170px]">
        <SelectValue :placeholder="presetPlaceholder" />
      </SelectTrigger>
      <SelectContent :body-lock="false">
        <SelectItem value="all">All time</SelectItem>
        <SelectItem value="last_7">Last 7 days</SelectItem>
        <SelectItem value="last_30">Last 30 days</SelectItem>
      </SelectContent>
    </Select>

    <Popover>
      <PopoverTrigger as-child>
        <Button variant="outline" class="w-[155px] justify-between font-normal">
          {{ formatDisplayDate(props.from) }}
          <CalendarIcon class="size-4 opacity-70" />
        </Button>
      </PopoverTrigger>
      <PopoverContent class="w-auto p-0" align="end">
        <Calendar
          :model-value="fromDateValue"
          @update:model-value="(value) => emit('update:from', value ? value.toString() : '')"
        />
      </PopoverContent>
    </Popover>

    <Popover>
      <PopoverTrigger as-child>
        <Button variant="outline" class="w-[155px] justify-between font-normal">
          {{ formatDisplayDate(props.to) }}
          <CalendarIcon class="size-4 opacity-70" />
        </Button>
      </PopoverTrigger>
      <PopoverContent class="w-auto p-0" align="end">
        <Calendar
          :model-value="toDateValue"
          @update:model-value="(value) => emit('update:to', value ? value.toString() : '')"
        />
      </PopoverContent>
    </Popover>
  </div>
</template>
