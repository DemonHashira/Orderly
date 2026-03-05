import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ordersKeys, dashboardKeys } from '@/lib/query-keys'
import {
  cancelOrder,
  confirmOrder,
  fetchOrder,
  fetchOrders,
  readyToShipOrder,
} from '@/features/orders/api/orders.api'
import type { OrderListParams } from '@/types'

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
    onSuccess: (_, orderId) => {
      void queryClient.invalidateQueries({ queryKey: ordersKeys.all })
      void queryClient.invalidateQueries({ queryKey: ordersKeys.detail(orderId) })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}
