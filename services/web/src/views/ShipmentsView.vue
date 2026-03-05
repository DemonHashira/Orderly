<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { formatDateTime } from '@/lib/formatters'
import {
  useMarkShipmentDeliveredMutation,
  useMarkShipmentReturnedMutation,
  useMarkShipmentUnpaidMutation,
  useShipmentsQuery,
} from '@/features/shipments/composables/useShipmentsQueries'
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

const shipmentsQuery = useShipmentsQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    tracking_number: debouncedSearch.value,
  })),
)

const deliveredMutation = useMarkShipmentDeliveredMutation()
const returnedMutation = useMarkShipmentReturnedMutation()
const unpaidMutation = useMarkShipmentUnpaidMutation()

const shipments = computed(() => shipmentsQuery.data.value?.data ?? [])
const meta = computed(() => shipmentsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(shipmentsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && shipmentsQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Shipments" description="Track delivery outcomes and follow-up states." />

    <Card>
      <CardContent class="pt-6">
        <DebouncedSearchInput v-model="search" placeholder="Search by tracking number" />
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="shipmentsQuery.error.value" message="Failed to load shipments." />

    <Card>
      <CardContent class="pt-6">
        <EmptyStateCard
          v-if="!shipmentsQuery.isLoading.value && shipments.length === 0"
          title="No shipments"
          description="No shipment data for current filters."
        />

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead>Order</TableHead>
              <TableHead>Courier</TableHead>
              <TableHead>Shipped</TableHead>
              <TableHead>Outcome</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="shipment in shipments" :key="shipment.id">
              <TableCell>
                <RouterLink :to="`/shipments/${shipment.id}`" class="font-medium hover:underline">
                  {{ shipment.order?.reference ?? `#${shipment.order_id}` }}
                </RouterLink>
              </TableCell>
              <TableCell>{{ shipment.courier }}</TableCell>
              <TableCell>{{ formatDateTime(shipment.shipped_at) }}</TableCell>
              <TableCell>
                <StatusBadge :status="shipment.order?.current_status ?? 'shipped'" />
              </TableCell>
              <TableCell class="text-right">
                <div class="flex justify-end gap-2">
                  <ConfirmActionDialog
                    title="Mark delivered"
                    description="Confirm this shipment was delivered."
                    confirm-label="Delivered"
                    @confirm="deliveredMutation.mutate(shipment.id)"
                  >
                    <template #trigger>
                      <Button size="sm" variant="outline">Delivered</Button>
                    </template>
                  </ConfirmActionDialog>

                  <ConfirmActionDialog
                    title="Mark returned"
                    description="Create a return flow from this shipment."
                    confirm-label="Returned"
                    @confirm="returnedMutation.mutate(shipment.id)"
                  >
                    <template #trigger>
                      <Button size="sm" variant="outline">Returned</Button>
                    </template>
                  </ConfirmActionDialog>

                  <ConfirmActionDialog
                    title="Mark unpaid"
                    description="Mark this shipment as unpaid and open a return flow."
                    confirm-label="Mark Unpaid"
                    @confirm="unpaidMutation.mutate(shipment.id)"
                  >
                    <template #trigger>
                      <Button size="sm" variant="destructive">Unpaid</Button>
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
