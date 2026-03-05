import type { DashboardRoleView, ResolveDashboardRoleViewInput } from './types'

export const resolveDashboardRoleView = ({
  permissions,
  roles,
}: ResolveDashboardRoleViewInput): DashboardRoleView => {
  const permissionSet = new Set(permissions)

  const canViewOrdersReport = permissionSet.has('reports.orders.view')
  const canViewInventoryReport = permissionSet.has('reports.inventory.view')
  const canViewReturnsReport = permissionSet.has('reports.returns.view')

  const hasShipmentOutcomePermission =
    permissionSet.has('shipments.outcome.delivered') ||
    permissionSet.has('shipments.outcome.returned') ||
    permissionSet.has('shipments.outcome.unpaid')

  if (canViewOrdersReport && canViewInventoryReport && canViewReturnsReport) {
    return 'owner'
  }

  if (!canViewOrdersReport && canViewInventoryReport && canViewReturnsReport) {
    return 'inventory'
  }

  if (canViewOrdersReport && !canViewInventoryReport && canViewReturnsReport) {
    return hasShipmentOutcomePermission ? 'logistics' : 'order_manager'
  }

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
