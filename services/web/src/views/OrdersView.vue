<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { formatCurrency } from '@/lib/formatters'
import { useAuth } from '@/features/auth/composables/useAuth'
import {
  useCancelOrderMutation,
  useConfirmOrderMutation,
  useOrdersQuery,
  useReadyToShipOrderMutation,
} from '@/features/orders/composables/useOrdersQueries'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  ConfirmActionDialog,
  DebouncedSearchInput,
  EmptyStateCard,
  PageInitialSkeleton,
  PageHeader,
  PageRefetchOverlay,
  ServerPagination,
  StatusBadge,
} from '@/shared/ui'

const page = ref(1)
const search = ref('')
const debouncedSearch = useDebouncedRef(search)

const { permissions } = useAuth()

const canConfirm = computed(() => permissions.value.includes('orders.status.confirm'))
const canReadyToShip = computed(() => permissions.value.includes('orders.status.ready_to_ship'))
const canCancel = computed(() => permissions.value.includes('orders.status.cancel'))

const ordersQuery = useOrdersQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    q: debouncedSearch.value,
  })),
)

const confirmMutation = useConfirmOrderMutation()
const readyMutation = useReadyToShipOrderMutation()
const cancelMutation = useCancelOrderMutation()

const orders = computed(() => ordersQuery.data.value?.data ?? [])
const meta = computed(() => ordersQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(ordersQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && ordersQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Orders" description="Manage order lifecycle transitions." />

    <Card>
      <CardContent class="pt-6">
        <DebouncedSearchInput v-model="search" placeholder="Search by reference or notes" />
      </CardContent>
    </Card>

    <ApiErrorAlert
      v-if="ordersQuery.error.value"
      message="Failed to load orders. Please refresh this page."
    />

    <Card>
      <CardContent class="pt-6">
        <EmptyStateCard
          v-if="!ordersQuery.isLoading.value && orders.length === 0"
          title="No orders found"
          description="Try changing your search filters."
        />

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead>Reference</TableHead>
              <TableHead>Status</TableHead>
              <TableHead class="text-right">Amount</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="order in orders" :key="order.id">
              <TableCell>
                <RouterLink :to="`/orders/${order.id}`" class="font-medium hover:underline">
                  {{ order.reference }}
                </RouterLink>
              </TableCell>
              <TableCell>
                <StatusBadge :status="order.current_status" />
              </TableCell>
              <TableCell class="text-right">{{ formatCurrency(order.total_amount) }}</TableCell>
              <TableCell class="text-right">
                <div class="flex justify-end gap-2">
                  <ConfirmActionDialog
                    v-if="canConfirm && order.current_status === 'draft'"
                    title="Confirm order"
                    description="This will move the order to confirmed status."
                    confirm-label="Confirm"
                    @confirm="confirmMutation.mutate(order.id)"
                  >
                    <template #trigger>
                      <Button size="sm" variant="outline">Confirm</Button>
                    </template>
                  </ConfirmActionDialog>

                  <ConfirmActionDialog
                    v-if="canReadyToShip && order.current_status === 'confirmed'"
                    title="Mark ready to ship"
                    description="This will hand off the order to logistics."
                    confirm-label="Mark Ready"
                    @confirm="readyMutation.mutate(order.id)"
                  >
                    <template #trigger>
                      <Button size="sm" variant="outline">Ready</Button>
                    </template>
                  </ConfirmActionDialog>

                  <ConfirmActionDialog
                    v-if="
                      canCancel &&
                      ['draft', 'confirmed', 'ready_to_ship'].includes(order.current_status)
                    "
                    title="Cancel order"
                    description="This action cannot be undone."
                    confirm-label="Cancel Order"
                    @confirm="cancelMutation.mutate(order.id)"
                  >
                    <template #trigger>
                      <Button size="sm" variant="destructive">Cancel</Button>
                    </template>
                  </ConfirmActionDialog>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>

    <ServerPagination
      v-if="meta"
      :current-page="meta.current_page"
      :total-pages="meta.last_page"
      @update:page="(nextPage) => (page = nextPage)"
    />
  </section>
</template>
