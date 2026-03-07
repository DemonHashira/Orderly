<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import OrderForm from '@/features/orders/ui/OrderForm.vue'
import { useAuth } from '@/features/auth/composables/useAuth'
import {
  useDeleteOrderMutation,
  useOrderQuery,
  useUpdateOrderMutation,
} from '@/features/orders/composables/useOrdersQueries'
import type { OrderUpsertPayload } from '@/features/orders/types'
import { useOrderCreateLookupQuery } from '@/features/lookups/composables/useOrderCreateLookupQuery'
import { useCustomersQuery } from '@/features/customers/composables/useCustomersQueries'
import { normalizeApiError } from '@/shared/api/errors'
import {
  ApiErrorAlert,
  ConfirmActionDialog,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
} from '@/shared/ui'

const route = useRoute()
const router = useRouter()
const { permissions } = useAuth()

const orderId = computed(() => Number(route.params.id))
const orderQuery = useOrderQuery(orderId)
const customersQuery = useCustomersQuery(
  computed(() => ({
    per_page: 100,
  })),
)
const lookupQuery = useOrderCreateLookupQuery()
const updateMutation = useUpdateOrderMutation()
const deleteMutation = useDeleteOrderMutation()

const fieldErrors = ref<Record<string, string>>({})
const submitError = ref('')
const deleteError = ref('')

const order = computed(() => orderQuery.data.value?.data ?? null)
const isDraftOrder = computed(() => order.value?.current_status === 'draft')
const canDelete = computed(() => permissions.value.includes('orders.delete'))

const isInitialLoading = computed(
  () => orderQuery.isLoading.value || customersQuery.isLoading.value || lookupQuery.isLoading.value,
)
const isRefreshing = computed(
  () =>
    !isInitialLoading.value &&
    (orderQuery.isFetching.value ||
      customersQuery.isFetching.value ||
      lookupQuery.isFetching.value),
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
  if (!order.value) {
    return
  }

  submitError.value = ''
  fieldErrors.value = {}

  try {
    const response = await updateMutation.mutateAsync({
      id: order.value.id,
      payload,
    })
    await router.push(`/orders/${response.data.id}`)
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    fieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    submitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const onDelete = async () => {
  if (!order.value) {
    return
  }

  deleteError.value = ''

  try {
    await deleteMutation.mutateAsync(order.value.id)
    await router.push('/orders')
  } catch (error: unknown) {
    deleteError.value = normalizeApiError(error).message
  }
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative mx-auto w-full max-w-4xl space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Edit Order" description="Update draft order details and line items.">
      <template #actions>
        <ConfirmActionDialog
          v-if="order && isDraftOrder && canDelete"
          title="Delete draft order"
          description="This will permanently delete the draft order."
          confirm-label="Delete Order"
          @confirm="onDelete"
        >
          <template #trigger>
            <Button variant="destructive" size="sm">Delete Order</Button>
          </template>
        </ConfirmActionDialog>
      </template>
    </PageHeader>

    <ApiErrorAlert
      v-if="orderQuery.error.value || customersQuery.error.value || lookupQuery.error.value"
      message="Failed to load order edit page."
    />

    <template v-else-if="order">
      <div
        v-if="!isDraftOrder"
        class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
      >
        Only draft orders can be edited.
      </div>

      <div
        v-if="deleteError"
        class="bg-destructive/10 text-destructive rounded-md border border-destructive/20 px-3 py-2 text-sm"
      >
        {{ deleteError }}
      </div>

      <OrderForm
        mode="edit"
        :initial-order="order"
        :customers="customersQuery.data.value?.data ?? []"
        :lookups="lookupQuery.data.value?.data ?? null"
        :is-submitting="updateMutation.isPending.value"
        :is-disabled="!isDraftOrder"
        :api-error="submitError"
        :server-field-errors="fieldErrors"
        @submit="onSubmit"
        @cancel="router.push(`/orders/${order.id}`)"
      />
    </template>
  </section>
</template>
