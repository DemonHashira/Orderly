import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { dashboardKeys, inventoryKeys, reportsKeys } from '@/lib/query-keys'
import {
  createInventoryMovement,
  fetchInventoryMovements,
  fetchInventoryStocks,
  type CreateMovementPayload,
} from '@/features/inventory/api/inventory.api'
import type { InventoryMovementsListParams, InventoryStocksListParams } from '@/types'

export const useInventoryStocksQuery = (
  params: MaybeRefOrGetter<InventoryStocksListParams>,
  options?: { enabled?: MaybeRefOrGetter<boolean>; keepPreviousData?: boolean },
) => {
  const queryParams = computed(() => toValue(params))
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )
  const keepPreviousData = options?.keepPreviousData ?? false

  return useQuery({
    queryKey: computed(() => inventoryKeys.stocks(queryParams.value)),
    queryFn: () => fetchInventoryStocks(queryParams.value),
    enabled: isEnabled,
    placeholderData: keepPreviousData ? (previousData) => previousData : undefined,
  })
}

export const useInventoryMovementsQuery = (
  params: MaybeRefOrGetter<InventoryMovementsListParams>,
  options?: { enabled?: MaybeRefOrGetter<boolean>; keepPreviousData?: boolean },
) => {
  const queryParams = computed(() => toValue(params))
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )
  const keepPreviousData = options?.keepPreviousData ?? false

  return useQuery({
    queryKey: computed(() => inventoryKeys.movements(queryParams.value)),
    queryFn: () => fetchInventoryMovements(queryParams.value),
    enabled: isEnabled,
    placeholderData: keepPreviousData ? (previousData) => previousData : undefined,
  })
}

export const useCreateInventoryMovementMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateMovementPayload) => createInventoryMovement(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: inventoryKeys.all })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
      void queryClient.invalidateQueries({ queryKey: reportsKeys.all })
    },
  })
}
