import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { productsKeys } from '@/lib/query-keys'
import {
  archiveProduct,
  createProduct,
  exportProducts,
  fetchProduct,
  fetchProducts,
  importProducts,
  updateProduct,
} from '@/features/products/api/products.api'
import type { ProductListParams } from '@/types'

export const useProductsQuery = (
  params: MaybeRefOrGetter<ProductListParams>,
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) => {
  const queryParams = computed(() => toValue(params))
  const isEnabled = computed(() =>
    options?.enabled === undefined ? true : Boolean(toValue(options.enabled)),
  )

  return useQuery({
    queryKey: computed(() => productsKeys.list(queryParams.value)),
    queryFn: () => fetchProducts(queryParams.value),
    enabled: isEnabled,
    placeholderData: (previousData) => previousData,
  })
}

export const useProductQuery = (id: MaybeRefOrGetter<number>) =>
  useQuery({
    queryKey: computed(() => productsKeys.detail(toValue(id))),
    queryFn: () => fetchProduct(toValue(id)),
    enabled: computed(() => toValue(id) > 0),
  })

export const useCreateProductMutation = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createProduct,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: productsKeys.all })
    },
  })
}

export const useUpdateProductMutation = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      updateProduct(id, payload),
    onSuccess: (_, variables) => {
      void queryClient.invalidateQueries({ queryKey: productsKeys.all })
      void queryClient.invalidateQueries({ queryKey: productsKeys.detail(variables.id) })
    },
  })
}

export const useArchiveProductMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: archiveProduct,
    onSuccess: (_, id) => {
      void queryClient.invalidateQueries({ queryKey: productsKeys.all })
      void queryClient.invalidateQueries({ queryKey: productsKeys.detail(id) })
    },
  })
}

export const useImportProductsMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: importProducts,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: productsKeys.all })
    },
  })
}

export const useExportProductsMutation = () =>
  useMutation({
    mutationFn: exportProducts,
  })
