import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import InventoryProductCombobox from '@/features/inventory/ui/InventoryProductCombobox.vue'

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

const baseOptions = [
  {
    id: 1,
    label: 'Desk Mousepad Series 3 (MERCH-MOUSEPAD-023)',
    sku: 'MERCH-MOUSEPAD-023',
  },
  {
    id: 2,
    label: 'Canvas Tote Bag Series 3 (MERCH-TOTE-023)',
    sku: 'MERCH-TOTE-023',
  },
]

describe('InventoryProductCombobox', () => {
  it('filters visible options in real time from the current search value', async () => {
    const wrapper = mount(InventoryProductCombobox, {
      props: {
        modelValue: '',
        searchValue: 'mouse',
        options: baseOptions,
      },
    })

    expect(wrapper.text()).toContain('Desk Mousepad Series 3')
    expect(wrapper.text()).not.toContain('Canvas Tote Bag Series 3')
  })

  it('emits search updates and clears the selected value when typing a new term', async () => {
    const wrapper = mount(InventoryProductCombobox, {
      props: {
        modelValue: '1',
        searchValue: 'Desk Mousepad Series 3 (MERCH-MOUSEPAD-023)',
        selectedLabel: 'Desk Mousepad Series 3 (MERCH-MOUSEPAD-023)',
        options: baseOptions,
      },
    })

    await wrapper.get('input').setValue('canvas')

    const emittedModelValues = wrapper.emitted('update:modelValue') ?? []
    const emittedSearchValues = wrapper.emitted('update:searchValue') ?? []

    expect(emittedModelValues[0]).toEqual([''])
    expect(emittedSearchValues[emittedSearchValues.length - 1]).toEqual(['canvas'])
  })

  it('marks option rows as clickable buttons with pointer cursor styling', () => {
    const wrapper = mount(InventoryProductCombobox, {
      props: {
        modelValue: '',
        searchValue: '',
        options: baseOptions,
      },
    })

    const optionButtons = wrapper
      .findAll('button')
      .filter((button) => button.text().includes('Series 3'))

    expect(optionButtons.length).toBeGreaterThan(0)
    expect(optionButtons[0]?.attributes('class')).toContain('cursor-pointer')
  })

  it('keeps showing filtered options during background loading instead of flashing a loading state', () => {
    const wrapper = mount(InventoryProductCombobox, {
      props: {
        modelValue: '',
        searchValue: 'test',
        loading: true,
        options: [
          {
            id: 9,
            label: 'Test Product (SKU-TEST)',
            sku: 'SKU-TEST',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('Test Product (SKU-TEST)')
    expect(wrapper.text()).not.toContain('Loading products…')
  })
})
