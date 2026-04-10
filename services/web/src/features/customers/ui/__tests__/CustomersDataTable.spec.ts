import { nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CustomersDataTable from '@/features/customers/ui/CustomersDataTable.vue'
import type { Customer } from '@/types'

const baseRows: Customer[] = [
  {
    id: 2,
    name: 'Zara Koleva',
    first_name: 'Zara',
    middle_name: null,
    last_name: 'Koleva',
    email: 'zara@example.com',
    phone: '+359881000102',
    address: {
      country: 'Bulgaria',
      city: 'Sofia',
      postal_code: '1000',
      address_line1: '2 Dondukov Blvd',
      address_line2: null,
    },
  },
  {
    id: 1,
    name: 'Anton Petrov',
    first_name: 'Anton',
    middle_name: null,
    last_name: 'Petrov',
    email: 'anton@example.com',
    phone: '+359881000101',
    address: {
      country: 'Bulgaria',
      city: 'Plovdiv',
      postal_code: '4000',
      address_line1: '15 Main Street',
      address_line2: 'Suite 4',
    },
  },
]

const selectStubs = {
  Select: { template: '<div><slot /></div>' },
  SelectTrigger: { template: '<button v-bind="$attrs"><slot /></button>' },
  SelectValue: { template: '<span><slot /></span>' },
  SelectContent: { template: '<div><slot /></div>' },
  SelectGroup: { template: '<div><slot /></div>' },
  SelectItem: { template: '<div><slot /></div>' },
}

const mountTable = () =>
  mount(CustomersDataTable, {
    props: {
      rows: baseRows,
      currentPage: 1,
      totalPages: 1,
      totalRows: baseRows.length,
      perPage: 15,
      canManageCustomers: true,
    },
    global: {
      stubs: {
        ...selectStubs,
        RouterLink: {
          props: ['to'],
          template: '<a :href="String(to)"><slot /></a>',
        },
        OverflowTooltipText: {
          props: ['text', 'triggerClass', 'dataTest'],
          template:
            '<span :data-test="dataTest" :data-text="text" :class="triggerClass">{{ text }}</span>',
        },
      },
    },
  })

describe('CustomersDataTable', () => {
  it('uses a fixed table layout for more even column distribution', () => {
    const wrapper = mountTable()

    expect(wrapper.get('[data-slot="table"]').classes()).toContain('table-fixed')
  })

  it('sorts rows by city when the city header is clicked', async () => {
    const wrapper = mountTable()

    const cityHeaderButton = wrapper
      .findAll('thead button')
      .find((button) => button.text().includes('City'))

    expect(cityHeaderButton).toBeDefined()

    await cityHeaderButton!.trigger('click')
    await nextTick()

    const bodyRows = wrapper.findAll('tbody tr')

    expect(bodyRows).toHaveLength(2)
    expect(bodyRows[0]?.text()).toContain('Anton Petrov')
    expect(bodyRows[1]?.text()).toContain('Zara Koleva')
  })
})
