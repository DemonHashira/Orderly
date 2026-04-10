import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import DateTimePickerInput from '@/shared/ui/DateTimePickerInput.vue'

vi.mock('@/components/ui/button', () => ({
  Button: defineComponent({
    inheritAttrs: false,
    template: '<button v-bind="$attrs"><slot /></button>',
  }),
}))

vi.mock('@/components/ui/popover', () => ({
  Popover: defineComponent({
    template: '<div><slot /></div>',
  }),
  PopoverTrigger: defineComponent({
    template: '<div><slot /></div>',
  }),
  PopoverContent: defineComponent({
    template: '<div><slot /></div>',
  }),
}))

vi.mock('@/components/ui/select', () => ({
  Select: defineComponent({
    template: '<div><slot /></div>',
  }),
  SelectContent: defineComponent({
    template: '<div><slot /></div>',
  }),
  SelectGroup: defineComponent({
    template: '<div><slot /></div>',
  }),
  SelectItem: defineComponent({
    props: {
      value: {
        type: String,
        required: false,
      },
    },
    template: '<div :data-value="value"><slot /></div>',
  }),
  SelectTrigger: defineComponent({
    template: '<button type="button"><slot /></button>',
  }),
  SelectValue: defineComponent({
    props: {
      placeholder: {
        type: String,
        required: false,
      },
    },
    template: '<span>{{ placeholder }}</span>',
  }),
}))

vi.mock('@/components/ui/calendar', () => ({
  Calendar: defineComponent({
    emits: ['update:modelValue'],
    template:
      '<button type="button" data-test="calendar-select" @click="$emit(`update:modelValue`, { toString: () => `2026-04-08` })">Select calendar day</button>',
  }),
}))

describe('DateTimePickerInput', () => {
  it('defaults the selected date to midnight when no time has been chosen yet', async () => {
    const wrapper = mount(DateTimePickerInput, {
      props: {
        modelValue: '',
      },
    })

    await wrapper.get('[data-test="calendar-select"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toEqual([['2026-04-08T00:00']])
  })
})
