import { describe, expect, it } from 'vitest'
import {
  buildInventoryReportViewModel,
  buildOrdersReportViewModel,
  buildReturnsReportViewModel,
} from '@/features/reports/model/report-view-models'

describe('report view models', () => {
  it('builds order report cards and highlights the top status', () => {
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
    })

    expect(model.cards).toHaveLength(3)
    expect(model.chartPoints[0]).toEqual({ label: 'delivered', value: 7 })
    expect(model.topStatus).toEqual({ label: 'delivered', value: 7 })
  })

  it('builds inventory report metrics including net movement', () => {
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
    })

    expect(model.cards).toHaveLength(4)
    expect(model.netMovement).toBe(45)
    expect(model.chartPoints).toEqual([
      { label: 'In', value: 120 },
      { label: 'Out', value: 75 },
    ])
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
    })

    expect(model.cards).toHaveLength(4)
    expect(model.restockRate).toBe(0)
    expect(model.writeOffRate).toBe(0)
    expect(model.chartPoints).toEqual([])
  })
})
