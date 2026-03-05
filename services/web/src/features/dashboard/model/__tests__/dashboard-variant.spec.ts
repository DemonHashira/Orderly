import { describe, expect, it } from 'vitest'
import { resolveDashboardVariant } from '@/features/dashboard/model'

describe('resolveDashboardVariant', () => {
  it('returns owner when all dashboard report permissions are present', () => {
    expect(
      resolveDashboardVariant({
        permissions: ['reports.orders.view', 'reports.inventory.view', 'reports.returns.view'],
      }),
    ).toBe('owner')
  })

  it('returns inventory when inventory+returns reports are present and orders report is missing', () => {
    expect(
      resolveDashboardVariant({
        permissions: ['reports.inventory.view', 'reports.returns.view'],
      }),
    ).toBe('inventory')
  })

  it('returns logistics when orders+returns reports are present and shipment outcome permissions exist', () => {
    expect(
      resolveDashboardVariant({
        permissions: ['reports.orders.view', 'reports.returns.view', 'shipments.outcome.delivered'],
      }),
    ).toBe('logistics')
  })

  it('returns order manager when orders+returns reports are present without shipment outcome permissions', () => {
    expect(
      resolveDashboardVariant({
        permissions: ['reports.orders.view', 'reports.returns.view'],
      }),
    ).toBe('order_manager')
  })

  it('falls back to role names for mixed permission sets', () => {
    expect(
      resolveDashboardVariant({
        permissions: ['dashboard.view'],
        roles: ['Logistics Manager'],
      }),
    ).toBe('logistics')
  })

  it('returns generic for unknown mixes with no role fallback', () => {
    expect(
      resolveDashboardVariant({
        permissions: ['dashboard.view'],
      }),
    ).toBe('generic')
  })
})
