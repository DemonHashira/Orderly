import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { formatCurrency, formatNumber } from '@/lib/formatters'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useDashboardSummaryQuery } from '@/features/dashboard/composables/useDashboardSummaryQuery'
import type { DashboardChartCard, DashboardKpiCard } from '@/features/dashboard/types'
import { mapCountMapToChart, mapInventoryFlowToChart } from '@/features/dashboard/ui/chart-adapters'
import { useOrdersQuery } from '@/features/orders/composables/useOrdersQueries'
import { useShipmentsQuery } from '@/features/shipments/composables/useShipmentsQueries'
import { useReturnsQuery } from '@/features/returns/composables/useReturnsQueries'
import { useInventoryStocksQuery } from '@/features/inventory/composables/useInventoryQueries'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'

export const useDashboardPageData = () => {
  const route = useRoute()
  const router = useRouter()
  const { permissions } = useAuth()
  const permissionSet = computed(() => new Set(permissions.value))

  const from = computed(() => (typeof route.query.from === 'string' ? route.query.from : undefined))
  const to = computed(() => (typeof route.query.to === 'string' ? route.query.to : undefined))
  const canViewOrdersReport = computed(() => permissionSet.value.has('reports.orders.view'))
  const canViewInventoryReport = computed(() => permissionSet.value.has('reports.inventory.view'))
  const canViewReturnsReport = computed(() => permissionSet.value.has('reports.returns.view'))
  const canFetchDashboardSummary = computed(
    () => canViewOrdersReport.value || canViewInventoryReport.value || canViewReturnsReport.value,
  )

  const dashboardQuery = useDashboardSummaryQuery(
    computed(() => ({
      from: from.value,
      to: to.value,
    })),
    {
      enabled: canFetchDashboardSummary,
    },
  )

  const canViewOrders = computed(() => permissionSet.value.has('orders.view'))
  const canViewShipments = computed(() => permissionSet.value.has('shipments.view'))
  const canViewReturns = computed(() => permissionSet.value.has('returns.view'))
  const canViewRestocks = computed(
    () =>
      canViewReturns.value &&
      (permissionSet.value.has('returns.restock') ||
        permissionSet.value.has('inventory.return_restock.approve')),
  )
  const canViewInventory = computed(() => permissionSet.value.has('inventory.view'))

  const readyOrdersQuery = useOrdersQuery(
    {
      status: 'ready_to_ship',
      per_page: 5,
    },
    { enabled: canViewOrders },
  )

  const shipmentsQuery = useShipmentsQuery(
    {
      per_page: 50,
    },
    { enabled: canViewShipments },
  )

  const returnsQuery = useReturnsQuery(
    {
      has_restockable: true,
      per_page: 12,
    },
    {
      enabled: canViewReturns,
      keepPreviousData: true,
    },
  )

  const stocksQuery = useInventoryStocksQuery(
    {
      per_page: 10,
    },
    { enabled: canViewInventory },
  )

  const dashboardData = computed(() => dashboardQuery.data.value?.data)

  const baseKpiCards = computed<Record<string, DashboardKpiCard | null>>(() => ({
    'orders-total': dashboardData.value?.orders
      ? {
          id: 'orders-total',
          title: 'Orders',
          value: formatNumber(dashboardData.value.orders.total_orders),
          description: 'Total orders in selected range',
        }
      : null,
    'orders-revenue': dashboardData.value?.orders
      ? {
          id: 'orders-revenue',
          title: 'Revenue',
          value: formatCurrency(dashboardData.value.orders.total_revenue),
          description: 'Total order revenue',
        }
      : null,
    'inventory-low-stock': dashboardData.value?.inventory
      ? {
          id: 'inventory-low-stock',
          title: 'Low Stock Alerts',
          value: formatNumber(dashboardData.value.inventory.low_stock_count),
          description: 'Products below reorder threshold',
        }
      : null,
    'inventory-available': dashboardData.value?.inventory
      ? {
          id: 'inventory-available',
          title: 'Available Units',
          value: formatNumber(dashboardData.value.inventory.total_available),
          description: 'Sellable stock units currently available',
        }
      : null,
    'returns-total': dashboardData.value?.returns
      ? {
          id: 'returns-total',
          title: 'Returns',
          value: formatNumber(dashboardData.value.returns.total_returns),
          description: 'Return orders in selected range',
        }
      : null,
  }))

  const baseChartCards = computed<Record<DashboardChartCard['id'], DashboardChartCard | null>>(
    () => ({
      'orders-by-status': dashboardData.value?.orders
        ? {
            id: 'orders-by-status',
            title: 'Orders by Status',
            description: 'Distribution in selected range',
            points: mapCountMapToChart(dashboardData.value.orders.by_status),
          }
        : null,
      'returns-by-outcome': dashboardData.value?.returns
        ? {
            id: 'returns-by-outcome',
            title: 'Returns by Outcome',
            description: 'Returned vs unpaid outcomes',
            points: mapCountMapToChart(dashboardData.value.returns.by_order_status),
          }
        : null,
      'inventory-flow': dashboardData.value?.inventory
        ? {
            id: 'inventory-flow',
            title: 'Inventory Flow',
            description: 'Movement in vs movement out',
            points: mapInventoryFlowToChart(
              dashboardData.value.inventory.movement_in_qty,
              dashboardData.value.inventory.movement_out_qty,
            ),
          }
        : null,
    }),
  )

  const readyOrders = computed(() => readyOrdersQuery.data.value?.data ?? [])
  const returnsToRestock = computed(() => returnsQuery.data.value?.data ?? [])
  const followUpShipments = computed(() => {
    const shipments = shipmentsQuery.data.value?.data ?? []

    return shipments
      .filter((shipment) => {
        const status = shipment.order?.current_status ?? ''
        return status === 'returned' || status === 'unpaid'
      })
      .slice(0, 5)
  })

  const lowAvailabilityStocks = computed(() => {
    const stocks = stocksQuery.data.value?.data ?? []
    return [...stocks].sort((a, b) => a.available - b.available).slice(0, 5)
  })

  const queueLoading = computed(() => ({
    readyToShip: canViewOrders.value && readyOrdersQuery.isLoading.value,
    returnsToRestock: canViewRestocks.value && returnsQuery.isLoading.value,
    shipmentFollowUp: canViewShipments.value && shipmentsQuery.isLoading.value,
    inventoryAttention: canViewInventory.value && stocksQuery.isLoading.value,
  }))

  const queueErrors = computed(() => ({
    readyToShip: canViewOrders.value && Boolean(readyOrdersQuery.error.value),
    returnsToRestock: canViewRestocks.value && Boolean(returnsQuery.error.value),
    shipmentFollowUp: canViewShipments.value && Boolean(shipmentsQuery.error.value),
    inventoryAttention: canViewInventory.value && Boolean(stocksQuery.error.value),
  }))

  const isInitialLoading = useInitialLoadingGate(
    computed(
      () =>
        dashboardQuery.isLoading.value ||
        queueLoading.value.readyToShip ||
        queueLoading.value.returnsToRestock ||
        queueLoading.value.shipmentFollowUp ||
        queueLoading.value.inventoryAttention,
    ),
  )

  const isRefetching = computed(
    () =>
      !isInitialLoading.value &&
      (dashboardQuery.isFetching.value ||
        readyOrdersQuery.isFetching.value ||
        returnsQuery.isFetching.value ||
        shipmentsQuery.isFetching.value ||
        stocksQuery.isFetching.value),
  )

  const updateQuery = async (next: Record<string, string | undefined>) => {
    await router.replace({
      query: {
        ...route.query,
        ...next,
      },
    })
  }

  const onPreset = async (preset: 'all' | 'last_7' | 'last_30') => {
    if (preset === 'all') {
      await updateQuery({ from: undefined, to: undefined })
      return
    }

    const end = new Date()
    const start = new Date()
    const days = preset === 'last_7' ? 7 : 30
    start.setDate(end.getDate() - (days - 1))

    const format = (date: Date) => {
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }

    await updateQuery({ from: format(start), to: format(end) })
  }

  return {
    from,
    to,
    onPreset,
    updateQuery,
    dashboardQuery,
    dashboardData,
    baseKpiCards,
    baseChartCards,
    readyOrders,
    returnsToRestock,
    followUpShipments,
    lowAvailabilityStocks,
    queueLoading,
    queueErrors,
    isInitialLoading,
    isRefetching,
    queuePermissions: {
      canViewOrders,
      canViewShipments,
      canViewReturns,
      canViewRestocks,
      canViewInventory,
    },
  }
}
