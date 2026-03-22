import {
  Archive,
  BarChart3,
  Boxes,
  ClipboardList,
  Package,
  Repeat2,
  ShoppingCart,
  Truck,
  Users,
} from 'lucide-vue-next'
import type { NavGroup, NavItem } from './types'

export type { NavGroup, NavItem } from './types'

export const NAV_ITEMS: NavItem[] = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    to: '/dashboard',
    icon: BarChart3,
    requiredPermission: 'dashboard.view',
    group: 'operations',
  },
  {
    id: 'reports-orders',
    label: 'Orders Report',
    to: '/reports/orders',
    icon: BarChart3,
    requiredPermission: 'reports.orders.view',
    group: 'reports',
  },
  {
    id: 'reports-inventory',
    label: 'Inventory Report',
    to: '/reports/inventory',
    icon: Boxes,
    requiredPermission: 'reports.inventory.view',
    group: 'reports',
  },
  {
    id: 'reports-returns',
    label: 'Returns Report',
    to: '/reports/returns',
    icon: Archive,
    requiredPermission: 'reports.returns.view',
    group: 'reports',
  },
  {
    id: 'orders',
    label: 'Orders',
    to: '/orders',
    icon: ShoppingCart,
    requiredPermission: 'orders.view',
    group: 'operations',
  },
  {
    id: 'shipments',
    label: 'Shipments',
    to: '/shipments',
    icon: Truck,
    requiredPermission: 'shipments.view',
    group: 'operations',
  },
  {
    id: 'returns',
    label: 'Returns',
    to: '/returns',
    icon: Archive,
    requiredPermission: 'returns.view',
    group: 'operations',
  },
  {
    id: 'inventory-stocks',
    label: 'Inventory Stocks',
    to: '/inventory/stocks',
    icon: Boxes,
    requiredPermission: 'inventory.view',
    group: 'inventory',
  },
  {
    id: 'inventory-movements',
    label: 'Inventory Movements',
    to: '/inventory/movements',
    icon: Repeat2,
    requiredPermission: 'inventory.view',
    group: 'inventory',
  },
  {
    id: 'products',
    label: 'Products',
    to: '/products',
    icon: Package,
    requiredPermission: 'products.view',
    group: 'catalog',
  },
  {
    id: 'customers',
    label: 'Customers',
    to: '/customers',
    icon: Users,
    requiredPermission: 'customers.view',
    group: 'catalog',
  },
  {
    id: 'team',
    label: 'Team',
    to: '/team',
    icon: Users,
    requiredPermission: 'users.manage',
    group: 'operations',
  },
]

export const NAV_GROUP_LABELS: Record<NavGroup, string> = {
  operations: 'Operations',
  reports: 'Reports',
  inventory: 'Inventory',
  catalog: 'Catalog',
}

export const filterNavByPermissions = (items: NavItem[], permissions: string[]): NavItem[] => {
  const permissionSet = new Set(permissions)

  return items.filter((item) => {
    if (!item.requiredPermission) {
      return true
    }

    return permissionSet.has(item.requiredPermission)
  })
}

export const findNavLabelByPath = (path: string): string => {
  const exact = NAV_ITEMS.find((item) => item.to === path)
  if (exact) {
    return exact.label
  }

  const prefixed = NAV_ITEMS.find((item) => path.startsWith(`${item.to}/`))
  return prefixed?.label ?? 'Orderly'
}

export const getQuickActionsByPermissions = (permissions: string[]) => {
  const permissionSet = new Set(permissions)

  const actions = [
    {
      id: 'orders',
      label: 'Review Ready Orders',
      description: 'Prioritize orders waiting for shipment handoff.',
      to: '/orders?status=ready_to_ship',
      icon: ClipboardList,
      requiredPermission: 'orders.view',
    },
    {
      id: 'returns',
      label: 'Review Returns',
      description: 'Review return records and restock status.',
      to: '/returns',
      icon: Archive,
      requiredPermission: 'returns.view',
    },
    {
      id: 'inventory',
      label: 'Check Low Stock',
      description: 'Inspect inventory shortages and movement history.',
      to: '/inventory/stocks',
      icon: Boxes,
      requiredPermission: 'inventory.view',
    },
  ]

  return actions.filter((action) => permissionSet.has(action.requiredPermission))
}
