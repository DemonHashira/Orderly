import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { customersKeys } from '@/lib/query-keys'
import {
  createCustomer,
  fetchCustomer,
  fetchCustomers,
  updateCustomer,
} from '@/features/customers/api/customers.api'
import type { CustomerListParams } from '@/types'

export const useCustomersQuery = (params: MaybeRefOrGetter<CustomerListParams>) => {
  const queryParams = computed(() => toValue(params))

  return useQuery({
    queryKey: computed(() => customersKeys.list(queryParams.value)),
    queryFn: () => fetchCustomers(queryParams.value),
  })
}

export const useCustomerQuery = (id: MaybeRefOrGetter<number>) =>
  useQuery({
    queryKey: computed(() => customersKeys.detail(toValue(id))),
    queryFn: () => fetchCustomer(toValue(id)),
    enabled: computed(() => toValue(id) > 0),
  })

export const useCreateCustomerMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createCustomer,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customersKeys.all })
    },
  })
}

export const useUpdateCustomerMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      updateCustomer(id, payload),
    onSuccess: (_, variables) => {
      void queryClient.invalidateQueries({ queryKey: customersKeys.all })
      void queryClient.invalidateQueries({ queryKey: customersKeys.detail(variables.id) })
    },
  })
}
