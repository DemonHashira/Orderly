import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { dashboardKeys, inventoryKeys, reportsKeys, returnsKeys } from '@/lib/query-keys'
import {
  addReturnItem,
  fetchReturn,
  fetchReturnByOrder,
  fetchReturns,
  restockReturn,
} from '@/features/returns/api/returns.api'
import type { ReturnListParams } from '@/types'
import type { AddReturnItemMutationPayload } from '@/features/returns/types'

export const useReturnsQuery = (
  params: MaybeRefOrGetter<ReturnListParams>,
  options?: {
    enabled?: MaybeRefOrGetter<boolean>
    keepPreviousData?: boolean
  },
) => {
  const queryParams = computed(() => toValue(params))
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )
  const keepPreviousData = options?.keepPreviousData ?? false

  return useQuery({
    queryKey: computed(() => returnsKeys.list(queryParams.value)),
    queryFn: () => fetchReturns(queryParams.value),
    enabled: isEnabled,
    placeholderData: keepPreviousData ? (previousData) => previousData : undefined,
  })
}

export const useReturnQuery = (id: MaybeRefOrGetter<number>) =>
  useQuery({
    queryKey: computed(() => returnsKeys.detail(toValue(id))),
    queryFn: () => fetchReturn(toValue(id)),
    enabled: computed(() => toValue(id) > 0),
  })

export const useReturnByOrderQuery = (orderId: MaybeRefOrGetter<number>) =>
  useQuery({
    queryKey: computed(() => returnsKeys.byOrder(toValue(orderId))),
    queryFn: () => fetchReturnByOrder(toValue(orderId)),
    enabled: computed(() => toValue(orderId) > 0),
  })

export const useRestockReturnMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: restockReturn,
    onSuccess: (_, returnId) => {
      void queryClient.invalidateQueries({ queryKey: returnsKeys.all })
      void queryClient.invalidateQueries({ queryKey: returnsKeys.detail(returnId) })
      void queryClient.invalidateQueries({ queryKey: inventoryKeys.all })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
      void queryClient.invalidateQueries({ queryKey: reportsKeys.all })
    },
  })
}

export const useAddReturnItemMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: AddReturnItemMutationPayload) => addReturnItem(id, payload),
    onSuccess: (response, variables) => {
      void queryClient.invalidateQueries({ queryKey: returnsKeys.all })
      void queryClient.invalidateQueries({ queryKey: returnsKeys.detail(variables.id) })
      void queryClient.invalidateQueries({ queryKey: returnsKeys.byOrder(response.data.order_id) })
      void queryClient.invalidateQueries({ queryKey: inventoryKeys.all })
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
      void queryClient.invalidateQueries({ queryKey: reportsKeys.all })
    },
  })
}
