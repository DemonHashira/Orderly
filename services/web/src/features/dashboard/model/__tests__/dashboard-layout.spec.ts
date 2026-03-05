import { describe, expect, it } from 'vitest'
import { resolveDashboardLayout } from '@/features/dashboard/model'

describe('resolveDashboardLayout', () => {
  it('orders inventory variant blocks by inventory-first priorities', () => {
    const layout = resolveDashboardLayout('inventory', {
      kpis: ['orders-total', 'inventory-low-stock', 'returns-total', 'orders-revenue'],
      charts: ['orders-by-status', 'inventory-flow', 'returns-by-outcome'],
      queues: ['ready-to-ship', 'inventory-attention', 'shipment-follow-up', 'returns-to-restock'],
    })

    expect(layout.kpis).toEqual([
      'inventory-low-stock',
      'returns-total',
      'orders-total',
      'orders-revenue',
    ])
    expect(layout.charts).toEqual(['inventory-flow', 'returns-by-outcome', 'orders-by-status'])
    expect(layout.queues).toEqual([
      'returns-to-restock',
      'inventory-attention',
      'ready-to-ship',
      'shipment-follow-up',
    ])
  })

  it('keeps deterministic order for generic variant', () => {
    const layout = resolveDashboardLayout('generic', {
      kpis: ['returns-total', 'orders-revenue', 'orders-total'],
      charts: ['returns-by-outcome', 'orders-by-status'],
      queues: ['inventory-attention', 'ready-to-ship'],
    })

    expect(layout.kpis).toEqual(['orders-total', 'orders-revenue', 'returns-total'])
    expect(layout.charts).toEqual(['orders-by-status', 'returns-by-outcome'])
    expect(layout.queues).toEqual(['ready-to-ship', 'inventory-attention'])
  })
})
