import { describe, expect, it } from 'vitest'
import {
  buildInventoryReportViewModel,
  buildOrdersReportViewModel,
  buildReturnsReportViewModel,
} from '@/features/reports/model/report-view-models'

describe('report view models', () => {
  it('builds order report comparison and table sections', () => {
    const model = buildOrdersReportViewModel({
      range: {
        from: '2026-03-01',
        to: '2026-03-31',
        is_all_time: false,
      },
      total_orders: 12,
      total_revenue: '1234.50',
      avg_order_value: '102.88',
      by_status: {
        delivered: 7,
        draft: 3,
        cancelled: 2,
      },
      comparison: {
        previous_range: {
          from: '2026-02-01',
          to: '2026-02-28',
        },
        metrics: {
          total_orders: {
            current: 12,
            previous: 9,
            delta: 3,
            direction: 'up',
            delta_percentage: 33.3,
          },
        },
      },
      breakdowns: {
        by_channel: [{ label: 'Retail', value: 7 }],
        top_products: [
          {
            product_id: 301,
            name: 'Winter Jacket',
            sku: 'JKT-301',
            quantity: 8,
            revenue: '792.00',
          },
        ],
      },
      exceptions: {
        backlog_orders: [
          {
            order_id: 101,
            reference: 'ORD-101',
            status: 'ready_to_ship',
            customer_name: 'Ada Lovelace',
            created_at: '2026-03-20T10:00:00.000000Z',
            age_days: 11,
            total_amount: '420.00',
          },
        ],
      },
      actions: [],
    })

    expect(model.cards).toHaveLength(3)
    expect(model.chartPoints[0]).toEqual({ label: 'delivered', value: 7 })
    expect(model.comparisonMetrics[0]?.label).toBe('Total orders')
    expect(model.breakdownSections[0]?.title).toBe('Sales channels')
    expect(model.exceptionSections[0]?.title).toBe('Backlog orders')
  })

  it('builds inventory report metrics including overview cards', () => {
    const model = buildInventoryReportViewModel({
      range: {
        from: '2026-03-01',
        to: '2026-03-31',
        is_all_time: false,
      },
      total_skus: 42,
      total_on_hand: 900,
      total_reserved: 110,
      total_available: 790,
      low_stock_count: 5,
      movement_in_qty: 120,
      movement_out_qty: 75,
      comparison: {
        previous_range: {
          from: '2026-02-01',
          to: '2026-02-28',
        },
        metrics: {
          total_available: {
            current: 790,
            previous: 720,
            delta: 70,
            direction: 'up',
            delta_percentage: 9.7,
          },
        },
      },
      breakdowns: {
        by_movement_type: [{ label: 'Restock', value: 120 }],
        by_reference_type: [{ label: 'Return', value: 120 }],
      },
      exceptions: {
        attention_items: [
          {
            product_id: 401,
            name: 'Archive Hoodie',
            sku: 'HD-401',
            status: 'low_stock',
            qty_on_hand: 3,
            qty_reserved: 2,
            qty_available: 1,
            reorder_threshold: 5,
            shortage_qty: 2,
          },
        ],
      },
      actions: [],
    })

    expect(model.cards).toHaveLength(4)
    expect(model.chartPoints).toEqual([
      { label: 'In', value: 120 },
      { label: 'Out', value: 75 },
    ])
    expect(model.overviewCards[1]?.label).toBe('Net movement')
    expect(model.breakdownSections[1]?.title).toBe('Movement source')
  })

  it('builds returns report rates safely for empty summaries', () => {
    const model = buildReturnsReportViewModel({
      range: {
        from: null,
        to: null,
        is_all_time: true,
      },
      total_returns: 0,
      total_return_items_qty: 0,
      restockable_items_qty: 0,
      non_restockable_items_qty: 0,
      by_order_status: {},
      breakdowns: {
        by_reason: [],
        by_channel: [],
        top_products: [],
      },
      exceptions: {
        pending_restock: [],
        write_off_products: [],
      },
      actions: [],
    })

    expect(model.cards).toHaveLength(4)
    expect(model.overviewCards[0]?.value).toBe('0.0%')
    expect(model.overviewCards[2]).toEqual({
      id: 'avg-items-per-return',
      label: 'Avg Items per Return',
      value: '0.0',
      description: 'Average returned item quantity per return order in the selected range.',
    })
    expect(model.zeroStateMessage).toBe('No returns recorded for the selected range.')
    expect(model.chartPoints).toEqual([])
  })
})
