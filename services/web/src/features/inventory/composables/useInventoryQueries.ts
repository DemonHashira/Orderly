import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { dashboardKeys, inventoryKeys } from '@/lib/query-keys'
import {
  createInventoryMovement,
  fetchInventoryMovements,
  fetchInventoryStocks,
  type CreateMovementPayload,
} from '@/features/inventory/api/inventory.api'
import type { InventoryMovementsListParams, InventoryStocksListParams } from '@/types'

export const useInventoryStocksQuery = (
  params: MaybeRefOrGetter<InventoryStocksListParams>,
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) => {
  const queryParams = computed(() => toValue(params))
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )

  return useQuery({
    queryKey: computed(() => inventoryKeys.stocks(queryParams.value)),
    queryFn: () => fetchInventoryStocks(queryParams.value),
    enabled: isEnabled,
  })
}

export const useInventoryMovementsQuery = (
  params: MaybeRefOrGetter<InventoryMovementsListParams>,
) => {
  const queryParams = computed(() => toValue(params))

  return useQuery({
    queryKey: computed(() => inventoryKeys.movements(queryParams.value)),
    queryFn: () => fetchInventoryMovements(queryParams.value),
  })
}

export const useCreateInventoryMovementMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateMovementPayload) => createInventoryMovement(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: inventoryKeys.all })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
    },
  })
}
