import { describe, expect, it } from 'vitest'
import { resolveDashboardRoleView, resolveDashboardVisibility } from '@/features/dashboard/model'

describe('dashboard permission consistency', () => {
  it('maps canonical owner permissions to owner view with all queue widgets visible', () => {
    const permissions = [
      'reports.orders.view',
      'reports.inventory.view',
      'reports.returns.view',
      'orders.view',
      'shipments.view',
      'returns.view',
      'returns.restock',
      'inventory.view',
    ]

    expect(resolveDashboardRoleView({ permissions, roles: ['Owner'] })).toBe('owner')

    const visibility = resolveDashboardVisibility({
      permissions,
      hasOrdersSummary: true,
      hasInventorySummary: true,
      hasReturnsSummary: true,
    })

    expect(visibility.queues).toEqual([
      'ready-to-ship',
      'shipment-follow-up',
      'returns-to-restock',
      'inventory-attention',
    ])
  })

  it('falls back to generic view for mixed custom permissions without role match', () => {
    const permissions = ['dashboard.view', 'shipments.view']
    expect(resolveDashboardRoleView({ permissions, roles: ['Supervisor'] })).toBe('generic')

    const visibility = resolveDashboardVisibility({
      permissions,
      hasOrdersSummary: false,
      hasInventorySummary: false,
      hasReturnsSummary: false,
    })

    expect(visibility.queues).toEqual(['shipment-follow-up'])
  })
})
