import type {
  DashboardChartBlockId,
  DashboardKpiBlockId,
  DashboardLayoutPlan,
  DashboardQueueBlockId,
  DashboardVariant,
} from './types'

const KPI_LAYOUTS: Record<DashboardVariant, DashboardKpiBlockId[]> = {
  owner: ['orders-total', 'orders-revenue', 'inventory-low-stock', 'returns-total'],
  order_manager: ['orders-total', 'orders-revenue', 'returns-total', 'inventory-low-stock'],
  logistics: ['orders-total', 'returns-total', 'orders-revenue', 'inventory-low-stock'],
  inventory: ['inventory-low-stock', 'returns-total', 'orders-total', 'orders-revenue'],
  generic: ['orders-total', 'orders-revenue', 'inventory-low-stock', 'returns-total'],
}

const CHART_LAYOUTS: Record<DashboardVariant, DashboardChartBlockId[]> = {
  owner: ['orders-by-status', 'returns-by-outcome', 'inventory-flow'],
  order_manager: ['orders-by-status', 'returns-by-outcome', 'inventory-flow'],
  logistics: ['orders-by-status', 'returns-by-outcome', 'inventory-flow'],
  inventory: ['inventory-flow', 'returns-by-outcome', 'orders-by-status'],
  generic: ['orders-by-status', 'returns-by-outcome', 'inventory-flow'],
}

const QUEUE_LAYOUTS: Record<DashboardVariant, DashboardQueueBlockId[]> = {
  owner: ['ready-to-ship', 'shipment-follow-up', 'returns-to-restock', 'inventory-attention'],
  order_manager: [
    'ready-to-ship',
    'shipment-follow-up',
    'returns-to-restock',
    'inventory-attention',
  ],
  logistics: ['ready-to-ship', 'shipment-follow-up', 'returns-to-restock', 'inventory-attention'],
  inventory: ['returns-to-restock', 'inventory-attention', 'ready-to-ship', 'shipment-follow-up'],
  generic: ['ready-to-ship', 'shipment-follow-up', 'returns-to-restock', 'inventory-attention'],
}

export const sortByPreferredOrder = <T extends string>(
  items: T[],
  preferredOrder: readonly T[],
) => {
  const order = new Map(preferredOrder.map((id, index) => [id, index]))

  return [...items].sort((left, right) => {
    const leftOrder = order.get(left) ?? Number.MAX_SAFE_INTEGER
    const rightOrder = order.get(right) ?? Number.MAX_SAFE_INTEGER

    if (leftOrder === rightOrder) {
      return left.localeCompare(right)
    }

    return leftOrder - rightOrder
  })
}

export const resolveDashboardLayout = (
  variant: DashboardVariant,
  visibility: {
    kpis: DashboardKpiBlockId[]
    charts: DashboardChartBlockId[]
    queues: DashboardQueueBlockId[]
  },
): DashboardLayoutPlan => {
  return {
    kpis: sortByPreferredOrder(visibility.kpis, KPI_LAYOUTS[variant]),
    charts: sortByPreferredOrder(visibility.charts, CHART_LAYOUTS[variant]),
    queues: sortByPreferredOrder(visibility.queues, QUEUE_LAYOUTS[variant]),
  }
}
