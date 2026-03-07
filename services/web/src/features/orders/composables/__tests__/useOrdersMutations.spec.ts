import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import * as ordersApi from '@/features/orders/api/orders.api'
import {
  useCreateOrderMutation,
  useDeleteOrderMutation,
  useUpdateOrderMutation,
} from '../useOrdersQueries'

describe('orders mutations', () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  })

  beforeEach(() => {
    vi.restoreAllMocks()
    queryClient.clear()
  })

  it('create mutation calls API and invalidates order queries', async () => {
    vi.spyOn(ordersApi, 'createOrder').mockResolvedValue({
      data: {
        id: 1,
        reference: 'ORD-1',
        customer_id: 1,
        sales_channel_id: 1,
        created_by: 1,
        current_status: 'draft',
        total_amount: '10.00',
        internal_notes: null,
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
      },
    })
    const invalidateQueries = vi.spyOn(queryClient, 'invalidateQueries')

    const TestComponent = defineComponent({
      setup() {
        const mutation = useCreateOrderMutation()
        return () =>
          h('button', {
            'data-testid': 'create-btn',
            onClick: () =>
              mutation.mutate({
                customer_id: 1,
                sales_channel_id: 1,
                internal_notes: null,
                items: [{ product_id: 1, quantity: 1, unit_price: null }],
              }),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="create-btn"]').trigger('click')
    await flushPromises()

    expect(ordersApi.createOrder).toHaveBeenCalledTimes(1)
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['orders'] })
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['dashboard'] })
  })

  it('update mutation calls API and invalidates list/detail/dashboard queries', async () => {
    vi.spyOn(ordersApi, 'updateOrder').mockResolvedValue({
      data: {
        id: 7,
        reference: 'ORD-7',
        customer_id: 1,
        sales_channel_id: 1,
        created_by: 1,
        current_status: 'draft',
        total_amount: '15.00',
        internal_notes: null,
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
      },
    })
    const invalidateQueries = vi.spyOn(queryClient, 'invalidateQueries')

    const TestComponent = defineComponent({
      setup() {
        const mutation = useUpdateOrderMutation()
        return () =>
          h('button', {
            'data-testid': 'update-btn',
            onClick: () =>
              mutation.mutate({
                id: 7,
                payload: {
                  customer_id: 1,
                  sales_channel_id: 1,
                  internal_notes: null,
                  items: [{ product_id: 1, quantity: 2, unit_price: null }],
                },
              }),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="update-btn"]').trigger('click')
    await flushPromises()

    expect(ordersApi.updateOrder).toHaveBeenCalledTimes(1)
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['orders'] })
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['orders', 'detail', 7] })
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['dashboard'] })
  })

  it('delete mutation calls API and invalidates/removes related queries', async () => {
    vi.spyOn(ordersApi, 'deleteOrder').mockResolvedValue(undefined)
    const invalidateQueries = vi.spyOn(queryClient, 'invalidateQueries')
    const removeQueries = vi.spyOn(queryClient, 'removeQueries')

    const TestComponent = defineComponent({
      setup() {
        const mutation = useDeleteOrderMutation()
        return () =>
          h('button', {
            'data-testid': 'delete-btn',
            onClick: () => mutation.mutate(9),
          })
      },
    })

    const wrapper = mount(TestComponent, {
      global: {
        plugins: [createPinia(), [VueQueryPlugin, { queryClient }]],
      },
    })

    await wrapper.find('[data-testid="delete-btn"]').trigger('click')
    await flushPromises()

    expect(ordersApi.deleteOrder).toHaveBeenCalledWith(9)
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['orders'] })
    expect(removeQueries).toHaveBeenCalledWith({ queryKey: ['orders', 'detail', 9] })
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['dashboard'] })
  })
})
