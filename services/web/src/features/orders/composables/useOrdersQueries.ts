import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ordersKeys, dashboardKeys } from '@/lib/query-keys'
import {
  cancelOrder,
  confirmOrder,
  createOrder,
  deleteOrder,
  fetchOrder,
  fetchOrders,
  readyToShipOrder,
  updateOrder,
} from '@/features/orders/api/orders.api'
import type { OrderListParams } from '@/types'
import type { Order, PaginatedResponse } from '@/types'
import type { OrderUpsertPayload } from '@/features/orders/types'

type OrdersListCache = PaginatedResponse<Order>
type OrderDetailCache = { data: Order }

const isOrdersListCache = (value: unknown): value is OrdersListCache => {
  if (!value || typeof value !== 'object') {
    return false
  }

  const candidate = value as Partial<OrdersListCache>
  return Array.isArray(candidate.data) && candidate.meta != null
}

const setOrderStatusInCachedLists = (
  queryClient: ReturnType<typeof useQueryClient>,
  orderId: number,
  nextStatus: string,
) => {
  const cachedQueries = queryClient.getQueriesData<OrdersListCache>({ queryKey: ordersKeys.all })

  cachedQueries.forEach(([queryKey, cacheValue]) => {
    if (!Array.isArray(queryKey) || queryKey[1] !== 'list' || !isOrdersListCache(cacheValue)) {
      return
    }

    const params = (queryKey[2] ?? {}) as Record<string, unknown>
    const statusFilter = typeof params.status === 'string' ? params.status : undefined
    const shouldRemainVisible = statusFilter == null || statusFilter === nextStatus
    const hadOrder = cacheValue.data.some((order) => order.id === orderId)

    if (!hadOrder) {
      return
    }

    const nextRows = cacheValue.data
      .map((order) => (order.id === orderId ? { ...order, current_status: nextStatus } : order))
      .filter((order) => (order.id !== orderId ? true : shouldRemainVisible))

    queryClient.setQueryData<OrdersListCache>(queryKey, {
      ...cacheValue,
      data: nextRows,
      meta: {
        ...cacheValue.meta,
        total: shouldRemainVisible ? cacheValue.meta.total : Math.max(0, cacheValue.meta.total - 1),
      },
    })
  })
}

const removeOrderFromCachedLists = (
  queryClient: ReturnType<typeof useQueryClient>,
  orderId: number,
) => {
  const cachedQueries = queryClient.getQueriesData<OrdersListCache>({ queryKey: ordersKeys.all })

  cachedQueries.forEach(([queryKey, cacheValue]) => {
    if (!Array.isArray(queryKey) || queryKey[1] !== 'list' || !isOrdersListCache(cacheValue)) {
      return
    }

    const hadOrder = cacheValue.data.some((order) => order.id === orderId)
    if (!hadOrder) {
      return
    }

    queryClient.setQueryData<OrdersListCache>(queryKey, {
      ...cacheValue,
      data: cacheValue.data.filter((order) => order.id !== orderId),
      meta: {
        ...cacheValue.meta,
        total: Math.max(0, cacheValue.meta.total - 1),
      },
    })
  })
}

const setOrderStatusInDetailCache = (
  queryClient: ReturnType<typeof useQueryClient>,
  orderId: number,
  nextStatus: string,
) => {
  queryClient.setQueryData<OrderDetailCache>(ordersKeys.detail(orderId), (existing) => {
    if (!existing) {
      return existing
    }
    return {
      ...existing,
      data: {
        ...existing.data,
        current_status: nextStatus,
      },
    }
  })
}

export const useOrdersQuery = (
  params: MaybeRefOrGetter<OrderListParams>,
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) => {
  const queryParams = computed(() => toValue(params))
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )

  return useQuery({
    queryKey: computed(() => ordersKeys.list(queryParams.value)),
    queryFn: () => fetchOrders(queryParams.value),
    enabled: isEnabled,
    placeholderData: (previousData) => previousData,
  })
}

export const useOrderQuery = (id: MaybeRefOrGetter<number>) => {
  return useQuery({
    queryKey: computed(() => ordersKeys.detail(toValue(id))),
    queryFn: () => fetchOrder(toValue(id)),
    enabled: computed(() => toValue(id) > 0),
  })
}

export const useConfirmOrderMutation = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: confirmOrder,
    onMutate: async (orderId) => {
      await queryClient.cancelQueries({ queryKey: ordersKeys.all })

      const previousLists = queryClient.getQueriesData<OrdersListCache>({
        queryKey: ordersKeys.all,
      })
      const previousDetail = queryClient.getQueryData<OrderDetailCache>(ordersKeys.detail(orderId))

      setOrderStatusInCachedLists(queryClient, orderId, 'confirmed')
      setOrderStatusInDetailCache(queryClient, orderId, 'confirmed')

      return { previousLists, previousDetail }
    },
    onError: (_error, orderId, context) => {
      context?.previousLists.forEach(([queryKey, cacheValue]) => {
        queryClient.setQueryData(queryKey, cacheValue)
      })
      queryClient.setQueryData(ordersKeys.detail(orderId), context?.previousDetail)
    },
    onSuccess: (_, orderId) => {
      void queryClient.invalidateQueries({ queryKey: ordersKeys.all })
      void queryClient.invalidateQueries({ queryKey: ordersKeys.detail(orderId) })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}

export const useReadyToShipOrderMutation = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: readyToShipOrder,
    onSuccess: (_, orderId) => {
      void queryClient.invalidateQueries({ queryKey: ordersKeys.all })
      void queryClient.invalidateQueries({ queryKey: ordersKeys.detail(orderId) })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}

export const useCancelOrderMutation = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: cancelOrder,
    onMutate: async (orderId) => {
      await queryClient.cancelQueries({ queryKey: ordersKeys.all })

      const previousLists = queryClient.getQueriesData<OrdersListCache>({
        queryKey: ordersKeys.all,
      })
      const previousDetail = queryClient.getQueryData<OrderDetailCache>(ordersKeys.detail(orderId))

      setOrderStatusInCachedLists(queryClient, orderId, 'cancelled')
      setOrderStatusInDetailCache(queryClient, orderId, 'cancelled')

      return { previousLists, previousDetail }
    },
    onError: (_error, orderId, context) => {
      context?.previousLists.forEach(([queryKey, cacheValue]) => {
        queryClient.setQueryData(queryKey, cacheValue)
      })
      queryClient.setQueryData(ordersKeys.detail(orderId), context?.previousDetail)
    },
    onSuccess: (_, orderId) => {
      void queryClient.invalidateQueries({ queryKey: ordersKeys.all })
      void queryClient.invalidateQueries({ queryKey: ordersKeys.detail(orderId) })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}

export const useCreateOrderMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: OrderUpsertPayload) => createOrder(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ordersKeys.all })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}

export const useUpdateOrderMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: OrderUpsertPayload }) =>
      updateOrder(id, payload),
    onSuccess: (_, variables) => {
      void queryClient.invalidateQueries({ queryKey: ordersKeys.all })
      void queryClient.invalidateQueries({ queryKey: ordersKeys.detail(variables.id) })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}

export const useDeleteOrderMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => deleteOrder(id),
    onMutate: async (orderId) => {
      await queryClient.cancelQueries({ queryKey: ordersKeys.all })

      const previousLists = queryClient.getQueriesData<OrdersListCache>({
        queryKey: ordersKeys.all,
      })
      const previousDetail = queryClient.getQueryData<OrderDetailCache>(ordersKeys.detail(orderId))

      removeOrderFromCachedLists(queryClient, orderId)
      queryClient.removeQueries({ queryKey: ordersKeys.detail(orderId) })

      return { previousLists, previousDetail }
    },
    onError: (_error, orderId, context) => {
      context?.previousLists.forEach(([queryKey, cacheValue]) => {
        queryClient.setQueryData(queryKey, cacheValue)
      })
      queryClient.setQueryData(ordersKeys.detail(orderId), context?.previousDetail)
    },
    onSuccess: (_, id) => {
      void queryClient.invalidateQueries({ queryKey: ordersKeys.all })
      void queryClient.removeQueries({ queryKey: ordersKeys.detail(id) })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}
