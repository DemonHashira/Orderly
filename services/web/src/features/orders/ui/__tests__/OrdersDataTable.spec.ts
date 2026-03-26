import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OrdersDataTable from '@/features/orders/ui/OrdersDataTable.vue'

const baseProps = {
  rows: [],
  currentPage: 1,
  totalPages: 3,
  totalRows: 12,
  perPage: 15,
  canConfirm: false,
  canReadyToShip: false,
  canCancel: false,
  canEditDraft: false,
  canDeleteDraft: false,
  canCreateShipment: false,
}

const selectStubs = {
  Select: { template: '<div><slot /></div>' },
  SelectTrigger: { template: '<button v-bind="$attrs"><slot /></button>' },
  SelectValue: { template: '<span><slot /></span>' },
  SelectContent: { template: '<div><slot /></div>' },
  SelectGroup: { template: '<div data-test="select-group"><slot /></div>' },
  SelectItem: { template: '<div><slot /></div>' },
}

describe('OrdersDataTable', () => {
  it('adds accessible labels to pagination and rows per page controls', () => {
    const wrapper = mount(OrdersDataTable, {
      props: baseProps,
      global: {
        stubs: selectStubs,
      },
    })

    expect(wrapper.get('[data-test="orders-per-page"]').attributes('aria-label')).toBe(
      'Rows per page',
    )
    expect(wrapper.find('button[aria-label="First page"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Previous page"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Next page"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Last page"]').exists()).toBe(true)
  })

  it('wraps rows per page options in a select group', () => {
    const wrapper = mount(OrdersDataTable, {
      props: baseProps,
      global: {
        stubs: selectStubs,
      },
    })

    expect(wrapper.findAll('[data-test="select-group"]')).toHaveLength(1)
  })
})
