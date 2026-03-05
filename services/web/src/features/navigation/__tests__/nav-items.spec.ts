import { describe, expect, it } from 'vitest'
import {
  filterNavByPermissions,
  findNavLabelByPath,
  NAV_ITEMS,
} from '@/features/navigation/nav-items'

describe('navigation config', () => {
  it('filters items by permissions', () => {
    const items = filterNavByPermissions(NAV_ITEMS, ['dashboard.view', 'orders.view'])

    expect(items.map((item) => item.id)).toEqual(['dashboard', 'orders'])
  })

  it('returns no items when user has no module permissions', () => {
    const items = filterNavByPermissions(NAV_ITEMS, [])
    expect(items).toEqual([])
  })

  it('resolves route label from exact and nested path', () => {
    expect(findNavLabelByPath('/shipments')).toBe('Shipments')
    expect(findNavLabelByPath('/orders/12')).toBe('Orders')
  })
})
