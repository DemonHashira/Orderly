import { describe, expect, it } from 'vitest'
import {
  filterNavByPermissions,
  findNavLabelByPath,
  getQuickActionsByPermissions,
  NAV_ITEMS,
} from '@/features/navigation/nav-items'

const ROLE_PERMISSIONS = {
  owner: [
    'dashboard.view',
    'reports.orders.view',
    'reports.inventory.view',
    'reports.returns.view',
    'orders.view',
    'shipments.view',
    'returns.view',
    'inventory.view',
    'products.view',
    'customers.view',
    'users.manage',
  ],
  orderManager: [
    'dashboard.view',
    'reports.orders.view',
    'reports.returns.view',
    'orders.view',
    'returns.view',
    'inventory.view',
    'products.view',
    'customers.view',
  ],
  logisticsManager: [
    'dashboard.view',
    'reports.orders.view',
    'reports.returns.view',
    'orders.view',
    'shipments.view',
    'returns.view',
    'inventory.view',
    'products.view',
    'customers.view',
  ],
  inventoryManager: [
    'dashboard.view',
    'reports.inventory.view',
    'reports.returns.view',
    'orders.view',
    'returns.view',
    'inventory.view',
    'products.view',
  ],
  queueOnly: ['dashboard.view', 'inventory.view', 'returns.view'],
} as const

describe('navigation config', () => {
  it('filters items by permissions', () => {
    const items = filterNavByPermissions(NAV_ITEMS, ['dashboard.view', 'orders.view'])

    expect(items.map((item) => item.id)).toEqual(['dashboard', 'orders'])
  })

  it('includes team when users.manage is present', () => {
    const items = filterNavByPermissions(NAV_ITEMS, ['users.manage'])
    expect(items.map((item) => item.id)).toEqual(['team'])
  })

  it('includes only accessible report items for mixed report permissions', () => {
    const items = filterNavByPermissions(NAV_ITEMS, ['reports.orders.view', 'reports.returns.view'])

    expect(items.map((item) => item.id)).toEqual(['reports-orders', 'reports-returns'])
  })

  it('returns no items when user has no module permissions', () => {
    const items = filterNavByPermissions(NAV_ITEMS, [])
    expect(items).toEqual([])
  })

  it('resolves route label from exact and nested path', () => {
    expect(findNavLabelByPath('/shipments')).toBe('Shipments')
    expect(findNavLabelByPath('/orders/12')).toBe('Orders')
    expect(findNavLabelByPath('/reports/inventory')).toBe('Inventory Report')
  })

  it('labels returns quick action as review-oriented for read-only roles', () => {
    const actions = getQuickActionsByPermissions(['returns.view'])

    expect(actions).toEqual([
      expect.objectContaining({
        id: 'returns',
        label: 'Review Returns',
      }),
    ])
  })

  it('deep-links the low-stock quick action into the actionable inventory view', () => {
    const actions = getQuickActionsByPermissions(['inventory.view'])

    expect(actions).toEqual([
      expect.objectContaining({
        id: 'inventory',
        to: '/inventory/stocks?stock_condition=low_stock&status=active',
      }),
    ])
  })

  it('keeps the shipped MVP navigation matrix aligned with role permissions', () => {
    expect(
      filterNavByPermissions(NAV_ITEMS, [...ROLE_PERMISSIONS.owner]).map((item) => item.id),
    ).toEqual([
      'dashboard',
      'reports-orders',
      'reports-inventory',
      'reports-returns',
      'orders',
      'shipments',
      'returns',
      'inventory-stocks',
      'inventory-movements',
      'products',
      'customers',
      'team',
    ])

    expect(
      filterNavByPermissions(NAV_ITEMS, [...ROLE_PERMISSIONS.orderManager]).map((item) => item.id),
    ).toEqual([
      'dashboard',
      'reports-orders',
      'reports-returns',
      'orders',
      'returns',
      'inventory-stocks',
      'inventory-movements',
      'products',
      'customers',
    ])

    expect(
      filterNavByPermissions(NAV_ITEMS, [...ROLE_PERMISSIONS.logisticsManager]).map(
        (item) => item.id,
      ),
    ).toEqual([
      'dashboard',
      'reports-orders',
      'reports-returns',
      'orders',
      'shipments',
      'returns',
      'inventory-stocks',
      'inventory-movements',
      'products',
      'customers',
    ])

    expect(
      filterNavByPermissions(NAV_ITEMS, [...ROLE_PERMISSIONS.inventoryManager]).map(
        (item) => item.id,
      ),
    ).toEqual([
      'dashboard',
      'reports-inventory',
      'reports-returns',
      'orders',
      'returns',
      'inventory-stocks',
      'inventory-movements',
      'products',
    ])

    expect(
      filterNavByPermissions(NAV_ITEMS, [...ROLE_PERMISSIONS.queueOnly]).map((item) => item.id),
    ).toEqual(['dashboard', 'returns', 'inventory-stocks', 'inventory-movements'])
  })
})
