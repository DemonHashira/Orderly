import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { customersKeys } from '@/lib/query-keys'
import {
  createCustomer,
  deleteCustomer,
  fetchCustomer,
  fetchCustomers,
  updateCustomer,
} from '@/features/customers/api/customers.api'
import type { CustomerListParams } from '@/types'
import type { CustomerUpsertPayload } from '@/features/customers/types'

type UseCustomersQueryOptions = {
  allPages?: MaybeRefOrGetter<boolean>
  enabled?: MaybeRefOrGetter<boolean>
}

export const useCustomersQuery = (
  params: MaybeRefOrGetter<CustomerListParams>,
  options?: UseCustomersQueryOptions,
) => {
  const queryParams = computed(() => toValue(params))
  const shouldFetchAllPages = computed(() =>
    options?.allPages === undefined ? false : Boolean(toValue(options.allPages)),
  )
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )

  return useQuery({
    queryKey: computed(() => [
      ...customersKeys.list(queryParams.value),
      shouldFetchAllPages.value ? 'all-pages' : 'single-page',
    ]),
    queryFn: async () => {
      const paramsValue = queryParams.value
      const firstPage = await fetchCustomers(paramsValue)

      if (!shouldFetchAllPages.value || firstPage.meta.last_page <= 1) {
        return firstPage
      }

      const allRows = [...firstPage.data]
      for (let currentPage = 2; currentPage <= firstPage.meta.last_page; currentPage += 1) {
        const pageResponse = await fetchCustomers({
          ...paramsValue,
          page: currentPage,
          per_page: firstPage.meta.per_page,
        })
        allRows.push(...pageResponse.data)
      }

      return {
        ...firstPage,
        data: allRows,
      }
    },
    enabled: isEnabled,
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
    mutationFn: ({ id, payload }: { id: number; payload: CustomerUpsertPayload }) =>
      updateCustomer(id, payload),
    onSuccess: (_, variables) => {
      void queryClient.invalidateQueries({ queryKey: customersKeys.all })
      void queryClient.invalidateQueries({ queryKey: customersKeys.detail(variables.id) })
    },
  })
}

export const useDeleteCustomerMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => deleteCustomer(id),
    onSuccess: (_, customerId) => {
      void queryClient.invalidateQueries({ queryKey: customersKeys.all })
      queryClient.removeQueries({ queryKey: customersKeys.detail(customerId) })
    },
  })
}
