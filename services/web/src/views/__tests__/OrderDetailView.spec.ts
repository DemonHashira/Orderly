import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import OrderDetailView from '@/views/OrderDetailView.vue'
import type { Order } from '@/types'

const authState = vi.hoisted(() => ({
  permissions: ['orders.view'] as string[],
}))

const orderState = vi.hoisted(() => ({
  detail: {
    id: 101,
    reference: 'ORD-101',
    customer_id: 12,
    customer_name: 'Simona Popova',
    sales_channel_id: 3,
    sales_channel_name: 'Online Store',
    created_by: 1,
    current_status: 'draft',
    total_amount: '287.10',
    internal_notes: null,
    created_at: '2026-03-07T10:00:00Z',
    updated_at: '2026-03-07T10:00:00Z',
    items: [
      {
        id: 1,
        product_id: 144,
        quantity: 3,
        unit_price: '68.00',
        total_price: '204.00',
        product: {
          id: 144,
          name: 'Extremely Long Product Name That Should Be Truncated In The Order Detail Table',
          sku: 'PRD-144',
        },
      },
    ],
    status_history: [],
  } as Order,
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => authState.permissions),
  }),
}))

vi.mock('@/features/orders/composables/useOrdersQueries', () => ({
  useOrderQuery: () => ({
    data: computed(() => ({ data: orderState.detail })),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
  }),
  useDeleteOrderMutation: () => ({
    mutateAsync: vi.fn(),
    isPending: ref(false),
  }),
}))

const OverflowTooltipTextStub = {
  props: ['text', 'dataTest'],
  template: '<p :data-test="dataTest">{{ text }}</p>',
}

describe('OrderDetailView', () => {
  beforeEach(() => {
    authState.permissions = ['orders.view']
  })

  const mountView = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/orders/:id', name: 'order-detail', component: OrderDetailView }],
    })

    await router.replace('/orders/101')

    return mount(OrderDetailView, {
      global: {
        plugins: [createPinia(), router],
        stubs: {
          OverflowTooltipText: OverflowTooltipTextStub,
        },
      },
    })
  }

  it('shows product names inline with truncated text metadata instead of a raw product id column', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Product')
    expect(wrapper.text()).not.toContain('Product ID')
    expect(wrapper.text()).toContain(
      'Extremely Long Product Name That Should Be Truncated In The Order Detail Table',
    )
    expect(wrapper.text()).toContain('ID #144')
    expect(wrapper.text()).toContain('SKU PRD-144')
    expect(wrapper.find('[data-test="tooltip-trigger"]').exists()).toBe(true)
  })
})
