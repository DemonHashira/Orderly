import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OrderForm from '@/features/orders/ui/OrderForm.vue'

const baseProps = {
  customers: [],
  lookups: {
    sales_channels: [],
    products: [],
  },
}

const selectStubs = {
  Select: { template: '<div><slot /></div>' },
  SelectTrigger: { template: '<button v-bind="$attrs"><slot /></button>' },
  SelectValue: { template: '<span><slot /></span>' },
  SelectContent: { template: '<div><slot /></div>' },
  SelectGroup: { template: '<div data-test="select-group"><slot /></div>' },
  SelectItem: { template: '<div><slot /></div>' },
}

describe('OrderForm', () => {
  it('renders a semantic form with a submit button', () => {
    const wrapper = mount(OrderForm, {
      props: {
        ...baseProps,
        mode: 'create',
      },
      global: {
        stubs: selectStubs,
      },
    })

    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.get('[data-test="order-form-submit"]').attributes('type')).toBe('submit')
  })

  it('shows a plus icon on the submit button in create mode', () => {
    const wrapper = mount(OrderForm, {
      props: {
        ...baseProps,
        mode: 'create',
      },
      global: {
        stubs: selectStubs,
      },
    })

    expect(wrapper.find('[data-test="order-form-submit"] svg').exists()).toBe(true)
  })

  it('does not show a plus icon on the submit button in edit mode', () => {
    const wrapper = mount(OrderForm, {
      props: {
        ...baseProps,
        mode: 'edit',
      },
      global: {
        stubs: selectStubs,
      },
    })

    expect(wrapper.find('[data-test="order-form-submit"] svg').exists()).toBe(false)
  })

  it('uses select groups and accessible remove item labels', () => {
    const wrapper = mount(OrderForm, {
      props: {
        ...baseProps,
        mode: 'create',
      },
      global: {
        stubs: selectStubs,
      },
    })

    expect(wrapper.findAll('[data-test="select-group"]')).toHaveLength(3)
    expect(wrapper.find('[aria-label="Remove item 1"]').exists()).toBe(true)
  })
})
