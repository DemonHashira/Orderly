<script setup lang="ts">
import { computed } from 'vue'
import { CalendarIcon } from 'lucide-vue-next'
import { getLocalTimeZone, parseDate, today } from '@internationalized/date'
import type { DateValue } from 'reka-ui'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

type ParsedDateTime = {
  date: string
  hour: string
  minute: string
}

const HOUR_OPTIONS = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'))
const MINUTE_OPTIONS = Array.from({ length: 12 }, (_, index) => String(index * 5).padStart(2, '0'))

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    dataTest?: string
    buttonClass?: string
    triggerId?: string
  }>(),
  {
    placeholder: 'dd.mm.yyyy, --:--',
    dataTest: undefined,
    buttonClass: undefined,
    triggerId: undefined,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const isValidLocalDate = (year: number, month: number, day: number) => {
  const date = new Date(year, month - 1, day)

  return (
    Number.isInteger(year) &&
    Number.isInteger(month) &&
    Number.isInteger(day) &&
    date.getFullYear() === year &&
    date.getMonth() === month - 1 &&
    date.getDate() === day
  )
}

const isValidLocalDateTime = (
  year: number,
  month: number,
  day: number,
  hours: number,
  minutes: number,
) => {
  const date = new Date(year, month - 1, day, hours, minutes, 0, 0)

  return (
    isValidLocalDate(year, month, day) && date.getHours() === hours && date.getMinutes() === minutes
  )
}

const toLocalParts = (value: Date): ParsedDateTime => ({
  date: `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`,
  hour: String(value.getHours()).padStart(2, '0'),
  minute: String(value.getMinutes()).padStart(2, '0'),
})

const parseModelValue = (value: string): ParsedDateTime | null => {
  if (!value) {
    return null
  }

  const trimmed = value.trim()
  const dateOnlyMatch = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (dateOnlyMatch) {
    const year = Number(dateOnlyMatch[1])
    const month = Number(dateOnlyMatch[2])
    const day = Number(dateOnlyMatch[3])

    if (!isValidLocalDate(year, month, day)) {
      return null
    }

    return {
      date: `${dateOnlyMatch[1]}-${dateOnlyMatch[2]}-${dateOnlyMatch[3]}`,
      hour: '00',
      minute: '00',
    }
  }

  const dateTimeMatch = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/)
  if (dateTimeMatch) {
    const [, yearPart = '', monthPart = '', dayPart = '', hourPart = '00', minutePart = '00'] =
      dateTimeMatch
    const year = Number(yearPart)
    const month = Number(monthPart)
    const day = Number(dayPart)
    const hours = Number(hourPart)
    const minutes = Number(minutePart)

    if (!isValidLocalDateTime(year, month, day, hours, minutes)) {
      return null
    }

    return {
      date: `${yearPart}-${monthPart}-${dayPart}`,
      hour: hourPart,
      minute: minutePart,
    }
  }

  const date = new Date(trimmed)
  if (Number.isNaN(date.getTime())) {
    return null
  }

  return toLocalParts(date)
}

const formatDisplayValue = (value: ParsedDateTime) => {
  const dateMatch = value.date.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!dateMatch) {
    return props.placeholder
  }

  const [, yearPart = '', monthPart = '', dayPart = ''] = dateMatch
  const year = Number(yearPart)
  const month = Number(monthPart)
  const day = Number(dayPart)
  const date = new Date(year, month - 1, day, Number(value.hour), Number(value.minute), 0, 0)

  if (Number.isNaN(date.getTime())) {
    return props.placeholder
  }

  return `${date.toLocaleDateString('en-GB')}, ${value.hour}:${value.minute}`
}

const buildModelValue = (date: string, hour: string, minute: string) => `${date}T${hour}:${minute}`

const parsedValue = computed(() => parseModelValue(props.modelValue))

const selectedDate = computed<DateValue | undefined>(() => {
  if (!parsedValue.value) {
    return undefined
  }

  try {
    return parseDate(parsedValue.value.date)
  } catch {
    return undefined
  }
})

const displayValue = computed(() =>
  parsedValue.value ? formatDisplayValue(parsedValue.value) : props.placeholder,
)

const applyDateTime = (date: string, hour: string, minute: string) => {
  emit('update:modelValue', buildModelValue(date, hour, minute))
}

const onSelectDate = (value?: DateValue) => {
  if (!value) {
    emit('update:modelValue', '')
    return
  }

  applyDateTime(
    value.toString(),
    parsedValue.value?.hour ?? '00',
    parsedValue.value?.minute ?? '00',
  )
}

const onSelectHour = (value: string) => {
  if (!parsedValue.value) {
    return
  }

  applyDateTime(parsedValue.value.date, value, parsedValue.value.minute)
}

const onSelectMinute = (value: string) => {
  if (!parsedValue.value) {
    return
  }

  applyDateTime(parsedValue.value.date, parsedValue.value.hour, value)
}

const setToday = () => {
  applyDateTime(
    today(getLocalTimeZone()).toString(),
    parsedValue.value?.hour ?? '00',
    parsedValue.value?.minute ?? '00',
  )
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
    <PopoverContent
      class="w-[min(36rem,calc(100vw-2rem))] p-0"
      align="start"
      side="bottom"
      :side-offset="8"
      :body-lock="false"
    >
      <div class="flex flex-col sm:flex-row">
        <div class="border-b p-3 sm:min-w-0 sm:flex-1 sm:border-r sm:border-b-0">
          <Calendar
            layout="month-and-year"
            :model-value="selectedDate"
            @update:model-value="onSelectDate"
          />
        </div>

        <div class="flex flex-col gap-3 p-3 sm:w-52 sm:shrink-0">
          <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
              <p class="text-xs font-medium text-muted-foreground">Hour</p>
              <Select
                :model-value="parsedValue?.hour"
                :disabled="!parsedValue"
                @update:model-value="(value) => value && onSelectHour(String(value))"
              >
                <SelectTrigger
                  class="w-full"
                  :data-test="dataTest ? `${dataTest}-hour` : undefined"
                >
                  <SelectValue placeholder="Hour" />
                </SelectTrigger>
                <SelectContent class="max-h-56" :body-lock="false">
                  <SelectGroup>
                    <SelectItem v-for="hour in HOUR_OPTIONS" :key="hour" :value="hour">
                      {{ hour }}
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>

            <div class="flex flex-col gap-1">
              <p class="text-xs font-medium text-muted-foreground">Minute</p>
              <Select
                :model-value="parsedValue?.minute"
                :disabled="!parsedValue"
                @update:model-value="(value) => value && onSelectMinute(String(value))"
              >
                <SelectTrigger
                  class="w-full"
                  :data-test="dataTest ? `${dataTest}-minute` : undefined"
                >
                  <SelectValue placeholder="Minute" />
                </SelectTrigger>
                <SelectContent class="max-h-56" :body-lock="false">
                  <SelectGroup>
                    <SelectItem v-for="minute in MINUTE_OPTIONS" :key="minute" :value="minute">
                      {{ minute }}
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div class="rounded-md border bg-muted/30 px-3 py-2">
            <p class="text-xs font-medium text-muted-foreground">Selected time</p>
            <p class="text-sm font-medium text-foreground">
              {{ parsedValue ? `${parsedValue.hour}:${parsedValue.minute}` : '--:--' }}
            </p>
          </div>

          <div class="mt-auto flex items-center justify-between gap-2">
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
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
