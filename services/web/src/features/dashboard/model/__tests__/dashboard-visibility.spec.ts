import { describe, expect, it } from 'vitest'
import { resolveDashboardVisibility } from '@/features/dashboard/model'

describe('resolveDashboardVisibility', () => {
  it('shows report-driven kpis/charts only when permission and summary data are available', () => {
    const visibility = resolveDashboardVisibility({
      permissions: ['reports.orders.view', 'reports.returns.view', 'orders.view', 'shipments.view'],
      hasOrdersSummary: true,
      hasInventorySummary: true,
      hasReturnsSummary: false,
    })

    expect(visibility.kpis).toEqual(['orders-total', 'orders-revenue'])
    expect(visibility.charts).toEqual(['orders-by-status'])
    expect(visibility.queues).toEqual(['ready-to-ship', 'shipment-follow-up'])
  })

  it('hides inventory kpi/chart when reports.inventory.view is missing', () => {
    const visibility = resolveDashboardVisibility({
      permissions: ['reports.orders.view', 'reports.returns.view'],
      hasOrdersSummary: true,
      hasInventorySummary: true,
      hasReturnsSummary: true,
    })

    expect(visibility.kpis).not.toContain('inventory-low-stock')
    expect(visibility.charts).not.toContain('inventory-flow')
  })

  it('hides restock queue when neither restock permission is present', () => {
    const visibility = resolveDashboardVisibility({
      permissions: ['returns.view', 'inventory.view'],
      hasOrdersSummary: false,
      hasInventorySummary: false,
      hasReturnsSummary: false,
    })

    expect(visibility.queues).not.toContain('returns-to-restock')
    expect(visibility.queues).toContain('inventory-attention')
  })

  it('hides restock queue when returns.view is missing', () => {
    const visibility = resolveDashboardVisibility({
      permissions: ['returns.restock', 'inventory.return_restock.approve'],
      hasOrdersSummary: false,
      hasInventorySummary: false,
      hasReturnsSummary: false,
    })

    expect(visibility.queues).not.toContain('returns-to-restock')
  })
})
