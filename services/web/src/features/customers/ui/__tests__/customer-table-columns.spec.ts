import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@/shared/ui', () => ({
  OverflowTooltipText: defineComponent({
    props: {
      text: {
        type: String,
        required: true,
      },
      triggerClass: {
        type: String,
        default: '',
      },
      dataTest: {
        type: String,
        default: undefined,
      },
    },
    template:
      '<span :data-test="dataTest" :data-text="text" :class="triggerClass">{{ text }}</span>',
  }),
}))

import { buildCustomersTableColumns } from '@/features/customers/ui/customer-table-columns'
import type { Customer } from '@/types'

const baseCustomer: Customer = {
  id: 42,
  name: 'Nina Petrova',
  first_name: 'Nina',
  middle_name: null,
  last_name: 'Petrova',
  email: 'nina.petkova@example.com',
  phone: '+359881000042',
  address: {
    country: 'Bulgaria',
    city: 'Sofia',
    postal_code: '1000',
    address_line1: '15 Vitosha Blvd',
    address_line2: 'Floor 3',
  },
}

const buildColumns = () =>
  buildCustomersTableColumns({
    canEditCustomers: true,
    canDeleteCustomers: true,
    onView: vi.fn(),
    onEdit: vi.fn(),
    onDelete: vi.fn(),
  })

const renderCellText = (customer: Customer, columnId: string) => {
  const column = buildColumns().find((entry) => entry.id === columnId)

  expect(column?.cell).toBeDefined()
  expect(typeof column?.cell).toBe('function')

  if (!column || typeof column.cell !== 'function') {
    throw new Error(`Expected ${columnId} column to expose a cell renderer`)
  }

  const vnode = column.cell({
    row: {
      original: customer,
    },
  } as never)

  return mount({
    render: () => vnode,
  }).text()
}

const renderCellWrapper = (customer: Customer, columnId: string) => {
  const column = buildColumns().find((entry) => entry.id === columnId)

  expect(column?.cell).toBeDefined()
  expect(typeof column?.cell).toBe('function')

  if (!column || typeof column.cell !== 'function') {
    throw new Error(`Expected ${columnId} column to expose a cell renderer`)
  }

  const vnode = column.cell({
    row: {
      original: customer,
    },
  } as never)

  return mount({
    render: () => vnode,
  })
}

const getAccessorColumn = (columnId: string) =>
  buildColumns().find((column) => column.id === columnId) as
    | {
        header?: unknown
        accessorFn?: (row: Customer, index: number) => unknown
      }
    | undefined

describe('buildCustomersTableColumns', () => {
  it('adds city, country, and compact address columns for customers with addresses', () => {
    const columns = buildColumns()

    expect(columns.map((column) => column.id)).toEqual([
      'name',
      'email',
      'phone',
      'city',
      'country',
      'address',
      'actions',
    ])

    expect(renderCellText(baseCustomer, 'city')).toContain('Sofia')
    expect(renderCellText(baseCustomer, 'country')).toContain('Bulgaria')
    expect(renderCellText(baseCustomer, 'address')).toContain('15 Vitosha Blvd, Floor 3')

    const cityColumn = getAccessorColumn('city')
    const countryColumn = getAccessorColumn('country')
    const addressColumn = getAccessorColumn('address')

    expect(typeof cityColumn?.header).toBe('function')
    expect(typeof countryColumn?.header).toBe('function')
    expect(typeof addressColumn?.header).toBe('function')

    expect(cityColumn?.accessorFn?.(baseCustomer, 0)).toBe('Sofia')
    expect(countryColumn?.accessorFn?.(baseCustomer, 0)).toBe('Bulgaria')
    expect(addressColumn?.accessorFn?.(baseCustomer, 0)).toBe('15 Vitosha Blvd, Floor 3')
  })

  it('renders dash placeholders when address data is missing', () => {
    const customerWithoutAddress: Customer = {
      ...baseCustomer,
      address: null,
    }

    expect(renderCellText(customerWithoutAddress, 'city')).toBe('-')
    expect(renderCellText(customerWithoutAddress, 'country')).toBe('-')
    expect(renderCellText(customerWithoutAddress, 'address')).toBe('-')
  })

  it('keeps the row actions column available after adding the new data columns', () => {
    const actionsColumn = buildColumns().find((column) => column.id === 'actions')

    expect(actionsColumn).toBeDefined()
    expect(typeof actionsColumn?.cell).toBe('function')

    const actionMeta = actionsColumn?.meta as { className?: string } | undefined
    const actionAlignment = actionsColumn?.meta as { align?: string } | undefined
    const addressMeta = buildColumns().find((column) => column.id === 'address')?.meta as
      | { className?: string }
      | undefined

    expect(actionMeta?.className).toBe(addressMeta?.className)
    expect(actionAlignment?.align).toBe('right')
  })

  it('uses the shared overflow tooltip for email cells and keeps extra spacing before phone', () => {
    const emailWrapper = renderCellWrapper(baseCustomer, 'email')
    const phoneWrapper = renderCellWrapper(baseCustomer, 'phone')
    const tooltipTrigger = emailWrapper.get('[data-test="customers-email-tooltip"]')

    expect(tooltipTrigger.attributes('data-text')).toBe(baseCustomer.email)
    expect(tooltipTrigger.classes()).toContain('truncate')
    expect(tooltipTrigger.classes()).toContain('pr-6')

    expect(phoneWrapper.classes()).toContain('pl-6')
  })

  it('uses the shared overflow tooltip for address cells', () => {
    const addressWrapper = renderCellWrapper(baseCustomer, 'address')
    const tooltipTrigger = addressWrapper.get('[data-test="customers-address-tooltip"]')

    expect(tooltipTrigger.attributes('data-text')).toBe('15 Vitosha Blvd, Floor 3')
    expect(tooltipTrigger.classes()).toContain('truncate')
  })
})
