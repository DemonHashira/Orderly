<script setup lang="ts">
import { computed } from 'vue'
import { CalendarIcon } from 'lucide-vue-next'
import { getLocalTimeZone, parseDate, today } from '@internationalized/date'
import type { DateValue } from 'reka-ui'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    dataTest?: string
    buttonClass?: string
    triggerId?: string
  }>(),
  {
    placeholder: 'dd.mm.yyyy',
    dataTest: undefined,
    buttonClass: undefined,
    triggerId: undefined,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const asLocalDate = (value: string): Date | null => {
  const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (match) {
    const year = Number(match[1])
    const month = Number(match[2])
    const day = Number(match[3])
    const date = new Date(year, month - 1, day)

    if (
      Number.isInteger(year) &&
      Number.isInteger(month) &&
      Number.isInteger(day) &&
      date.getFullYear() === year &&
      date.getMonth() === month - 1 &&
      date.getDate() === day
    ) {
      return date
    }

    return null
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const normalizedDateValue = computed(() => {
  const match = props.modelValue.match(/^(\d{4})-(\d{2})-(\d{2})(?:$|T)/)
  if (!match) {
    return ''
  }

  return `${match[1]}-${match[2]}-${match[3]}`
})

const selectedDate = computed<DateValue | undefined>(() => {
  if (!normalizedDateValue.value) {
    return undefined
  }

  try {
    return parseDate(normalizedDateValue.value)
  } catch {
    return undefined
  }
})

const displayValue = computed(() => {
  if (!props.modelValue) {
    return props.placeholder
  }

  const date = asLocalDate(props.modelValue)
  if (!date) {
    return props.placeholder
  }

  return date.toLocaleDateString('en-GB')
})

const onSelectDate = (value?: DateValue) => {
  emit('update:modelValue', value ? value.toString() : '')
}

const setToday = () => {
  emit('update:modelValue', today(getLocalTimeZone()).toString())
}

const clearDate = () => {
  emit('update:modelValue', '')
}
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button
        type="button"
        variant="outline"
        :id="triggerId"
        class="justify-between font-normal"
        :class="buttonClass"
        :data-test="dataTest"
      >
        <span class="truncate">{{ displayValue }}</span>
        <CalendarIcon class="size-4 opacity-70" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-auto p-0" align="end" :body-lock="false">
      <div class="border-b p-3">
        <Calendar
          layout="month-and-year"
          :model-value="selectedDate"
          @update:model-value="onSelectDate"
        />
      </div>
      <div class="flex items-center justify-between p-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          :data-test="dataTest ? `${dataTest}-clear` : undefined"
          @click="clearDate"
        >
          Clear
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          :data-test="dataTest ? `${dataTest}-today` : undefined"
          @click="setToday"
        >
          Today
        </Button>
      </div>
    </PopoverContent>
  </Popover>
</template>
