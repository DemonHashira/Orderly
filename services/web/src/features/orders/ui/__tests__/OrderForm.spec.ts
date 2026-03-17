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

describe('OrderForm', () => {
  it('shows a plus icon on the submit button in create mode', () => {
    const wrapper = mount(OrderForm, {
      props: {
        ...baseProps,
        mode: 'create',
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
    })

    expect(wrapper.find('[data-test="order-form-submit"] svg').exists()).toBe(false)
  })
})
