import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { dashboardKeys, shipmentsKeys } from '@/lib/query-keys'
import {
  fetchShipment,
  fetchShipments,
  markShipmentDelivered,
  markShipmentReturned,
  markShipmentUnpaid,
} from '@/features/shipments/api/shipments.api'
import type { ShipmentListParams } from '@/types'

export const useShipmentsQuery = (
  params: MaybeRefOrGetter<ShipmentListParams>,
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) => {
  const queryParams = computed(() => toValue(params))
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )

  return useQuery({
    queryKey: computed(() => shipmentsKeys.list(queryParams.value)),
    queryFn: () => fetchShipments(queryParams.value),
    enabled: isEnabled,
  })
}

export const useShipmentQuery = (id: MaybeRefOrGetter<number>) => {
  return useQuery({
    queryKey: computed(() => shipmentsKeys.detail(toValue(id))),
    queryFn: () => fetchShipment(toValue(id)),
    enabled: computed(() => toValue(id) > 0),
  })
}

const useShipmentStatusMutation = (mutationFn: (id: number) => Promise<unknown>) => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn,
    onSuccess: (_, shipmentId) => {
      void queryClient.invalidateQueries({ queryKey: shipmentsKeys.all })
      void queryClient.invalidateQueries({ queryKey: shipmentsKeys.detail(shipmentId) })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}

export const useMarkShipmentDeliveredMutation = () =>
  useShipmentStatusMutation(markShipmentDelivered)
export const useMarkShipmentReturnedMutation = () => useShipmentStatusMutation(markShipmentReturned)
export const useMarkShipmentUnpaidMutation = () => useShipmentStatusMutation(markShipmentUnpaid)
