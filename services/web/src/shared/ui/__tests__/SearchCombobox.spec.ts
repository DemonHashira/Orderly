import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import SearchCombobox from '@/shared/ui/SearchCombobox.vue'

vi.mock('@/components/ui/button', () => ({
  Button: defineComponent({
    inheritAttrs: false,
    template: '<button v-bind="$attrs"><slot /></button>',
  }),
}))

vi.mock('@/components/ui/input', () => ({
  Input: defineComponent({
    inheritAttrs: false,
    props: {
      modelValue: {
        type: String,
        default: '',
      },
    },
    emits: ['update:modelValue', 'focus'],
    template:
      '<input v-bind="$attrs" :value="modelValue" @focus="$emit(\'focus\', $event)" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  }),
}))

vi.mock('@/components/ui/popover', () => ({
  Popover: defineComponent({
    template: '<div><slot /></div>',
  }),
  PopoverAnchor: defineComponent({
    template: '<div><slot /></div>',
  }),
  PopoverTrigger: defineComponent({
    template: '<div><slot /></div>',
  }),
  PopoverContent: defineComponent({
    template: '<div><slot /></div>',
  }),
}))

vi.mock('@/components/ui/overlay-scroll/OverlayScrollViewport.vue', () => ({
  default: defineComponent({
    template: '<div><slot /></div>',
  }),
}))

const baseOptions = [
  {
    key: 'mouse',
    label: 'Desk Mousepad Series 3 (MERCH-MOUSEPAD-023)',
  },
  {
    key: 'tote',
    label: 'Canvas Tote Bag Series 3 (MERCH-TOTE-023)',
  },
]

describe('SearchCombobox', () => {
  it('filters visible options in real time from the current input value', () => {
    const wrapper = mount(SearchCombobox, {
      props: {
        inputValue: 'mouse',
        options: baseOptions,
      },
    })

    expect(wrapper.text()).toContain('Desk Mousepad Series 3')
    expect(wrapper.text()).not.toContain('Canvas Tote Bag Series 3')
  })

  it('renders the custom option when one is provided', () => {
    const wrapper = mount(SearchCombobox, {
      props: {
        inputValue: 'test courier',
        options: ['DHL', 'Speedy'].map((option) => ({
          key: option,
          label: option,
        })),
        customOption: {
          key: 'test courier',
          label: 'Use "test courier"',
        },
      },
    })

    expect(wrapper.text()).toContain('Use "test courier"')
  })
})
