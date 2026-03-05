import type { DashboardVariant, ResolveDashboardVariantInput } from './types'

const hasPermission = (permissions: Set<string>, permission: string) => permissions.has(permission)

const hasAnyPermission = (permissions: Set<string>, required: string[]) =>
  required.some((permission) => permissions.has(permission))

export const resolveDashboardVariant = ({
  permissions,
  roles = [],
}: ResolveDashboardVariantInput): DashboardVariant => {
  const permissionSet = new Set(permissions)

  const canViewOrdersReport = hasPermission(permissionSet, 'reports.orders.view')
  const canViewInventoryReport = hasPermission(permissionSet, 'reports.inventory.view')
  const canViewReturnsReport = hasPermission(permissionSet, 'reports.returns.view')

  const hasShipmentOutcomePermission = hasAnyPermission(permissionSet, [
    'shipments.outcome.delivered',
    'shipments.outcome.returned',
    'shipments.outcome.unpaid',
  ])

  if (canViewOrdersReport && canViewInventoryReport && canViewReturnsReport) {
    return 'owner'
  }

  if (!canViewOrdersReport && canViewInventoryReport && canViewReturnsReport) {
    return 'inventory'
  }

  if (canViewOrdersReport && !canViewInventoryReport && canViewReturnsReport) {
    return hasShipmentOutcomePermission ? 'logistics' : 'order_manager'
  }

  // Role names are used only as fallback when permissions are custom/mixed.
  if (roles.includes('Owner')) {
    return 'owner'
  }
  if (roles.includes('Inventory Manager')) {
    return 'inventory'
  }
  if (roles.includes('Logistics Manager')) {
    return 'logistics'
  }
  if (roles.includes('Order Manager')) {
    return 'order_manager'
  }

  return 'generic'
}
