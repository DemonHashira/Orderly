import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'

vi.mock('@/shared/ui', () => ({
  StatusBadge: defineComponent({
    props: {
      status: {
        type: String,
        required: true,
      },
    },
    template: '<span>{{ status }}</span>',
  }),
}))

import { buildReturnsTableColumns } from '@/features/returns/ui/returns-table-columns'

const baseReturnOrder = {
  id: 11,
  order_id: 101,
  reason: 'Returned by customer',
  returned_at: '2026-04-08T14:28:00Z',
  restocked_at: null as string | null,
  created_at: '2026-04-08T14:28:00Z',
  updated_at: '2026-04-08T14:28:00Z',
  order: {
    id: 101,
    reference: 'OC-2026-0206',
    current_status: 'returned',
    customer_id: 12,
  },
  items: [
    {
      id: 701,
      product_id: 301,
      quantity: 1,
      restockable: true,
      product: {
        id: 301,
        name: 'Winter Jacket',
        sku: 'JKT-301',
      },
    },
  ],
}

describe('buildReturnsTableColumns', () => {
  it('shows a restocked label in the order status cell once the return is completed', () => {
    const columns = buildReturnsTableColumns({
      canRestock: true,
      onRestock: vi.fn(),
    })

    const statusColumn = columns.find((column) => column.id === 'order_status')
    expect(statusColumn?.cell).toBeDefined()
    expect(typeof statusColumn?.cell).toBe('function')

    if (!statusColumn || typeof statusColumn.cell !== 'function') {
      throw new Error('Expected order_status column to expose a cell renderer')
    }

    const vnode = statusColumn.cell({
      row: {
        original: {
          ...baseReturnOrder,
          restocked_at: '2026-04-08T15:00:00Z',
        },
      },
    } as never)

    const wrapper = mount({
      render: () => vnode,
    })

    expect(wrapper.text()).toContain('returned')
    expect(wrapper.text()).toContain('Restocked')
  })
})
