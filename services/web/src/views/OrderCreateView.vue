<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import OrderForm from '@/features/orders/ui/OrderForm.vue'
import { useCreateOrderMutation } from '@/features/orders/composables/useOrdersQueries'
import { useOrderCreateLookupQuery } from '@/features/lookups/composables/useOrderCreateLookupQuery'
import { useCustomersQuery } from '@/features/customers/composables/useCustomersQueries'
import type { OrderUpsertPayload } from '@/features/orders/types'
import { normalizeApiError } from '@/shared/api/errors'
import { ApiErrorAlert, PageHeader, PageInitialSkeleton, PageRefetchOverlay } from '@/shared/ui'

const router = useRouter()
const createMutation = useCreateOrderMutation()
const customersQuery = useCustomersQuery(
  computed(() => ({
    per_page: 100,
  })),
)
const lookupQuery = useOrderCreateLookupQuery()

const fieldErrors = ref<Record<string, string>>({})
const submitError = ref('')

const isInitialLoading = computed(
  () => customersQuery.isLoading.value || lookupQuery.isLoading.value,
)
const isRefreshing = computed(
  () =>
    !isInitialLoading.value && (customersQuery.isFetching.value || lookupQuery.isFetching.value),
)

const mapFieldErrors = (errors?: Record<string, string[]>) => {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages?.[0] ?? 'Invalid value']),
  )
}

const onSubmit = async (payload: OrderUpsertPayload) => {
  submitError.value = ''
  fieldErrors.value = {}

  try {
    const response = await createMutation.mutateAsync(payload)
    await router.push(`/orders/${response.data.id}`)
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    fieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    submitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative mx-auto w-full max-w-4xl space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Create Order"
      description="Create a draft order with items and optional notes."
    />

    <ApiErrorAlert
      v-if="customersQuery.error.value || lookupQuery.error.value"
      message="Failed to load order form lookups."
    />

    <OrderForm
      v-else
      mode="create"
      :customers="customersQuery.data.value?.data ?? []"
      :lookups="lookupQuery.data.value?.data ?? null"
      :is-submitting="createMutation.isPending.value"
      :api-error="submitError"
      :server-field-errors="fieldErrors"
      @submit="onSubmit"
      @cancel="router.push('/orders')"
    />
  </section>
</template>
