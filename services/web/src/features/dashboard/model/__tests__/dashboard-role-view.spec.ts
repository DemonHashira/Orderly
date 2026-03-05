import { describe, expect, it } from 'vitest'
import { resolveDashboardRoleView } from '@/features/dashboard/model'

describe('resolveDashboardRoleView', () => {
  it('resolves owner from report permissions', () => {
    expect(
      resolveDashboardRoleView({
        permissions: ['reports.orders.view', 'reports.inventory.view', 'reports.returns.view'],
        roles: [],
      }),
    ).toBe('owner')
  })

  it('resolves inventory from report permissions', () => {
    expect(
      resolveDashboardRoleView({
        permissions: ['reports.inventory.view', 'reports.returns.view'],
        roles: [],
      }),
    ).toBe('inventory')
  })

  it('resolves logistics from report and shipment outcome permissions', () => {
    expect(
      resolveDashboardRoleView({
        permissions: ['reports.orders.view', 'reports.returns.view', 'shipments.outcome.delivered'],
        roles: [],
      }),
    ).toBe('logistics')
  })

  it('resolves order manager from report permissions without shipment outcomes', () => {
    expect(
      resolveDashboardRoleView({
        permissions: ['reports.orders.view', 'reports.returns.view'],
        roles: [],
      }),
    ).toBe('order_manager')
  })

  it('falls back to role names for mixed permission sets', () => {
    expect(
      resolveDashboardRoleView({
        permissions: ['dashboard.view'],
        roles: ['Inventory Manager'],
      }),
    ).toBe('inventory')
  })

  it('falls back to generic for unknown role sets', () => {
    expect(
      resolveDashboardRoleView({
        permissions: ['dashboard.view'],
        roles: ['Supervisor'],
      }),
    ).toBe('generic')
    expect(resolveDashboardRoleView({ permissions: [], roles: [] })).toBe('generic')
  })
})
