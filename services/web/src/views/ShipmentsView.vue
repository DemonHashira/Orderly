<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
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
import { BASIC_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'shipments' as const
const isSyncingFromRoute = ref(false)

const page = computed({
  get: () => listUiStore.modules[listModule].page,
  set: (value: number) => listUiStore.setState(listModule, { page: value }),
})
const search = computed({
  get: () => listUiStore.modules[listModule].q,
  set: (value: string) => listUiStore.setState(listModule, { q: value }),
})
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

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>
    if (!listUiStore.hasRelevantQuery(normalizedQuery, BASIC_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, BASIC_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, BASIC_LIST_FIELDS)
    isSyncingFromRoute.value = false
  },
  { immediate: true },
)

watch(search, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch([debouncedSearch, page], () => {
  if (isSyncingFromRoute.value) {
    return
  }

  const nextQuery = {
    ...listUiStore.toQuery(listModule, BASIC_LIST_FIELDS),
    ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
  }
  const currentQuery = listUiStore.normalizeQuery(
    listModule,
    route.query as Record<string, unknown>,
    BASIC_LIST_FIELDS,
  )

  if (JSON.stringify(nextQuery) === JSON.stringify(currentQuery)) {
    return
  }

  void router.replace({
    query: nextQuery,
  })
})
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

    <EmptyStateCard
      v-if="!shipmentsQuery.isLoading.value && shipments.length === 0"
      title="No shipments"
      description="No shipment data for current filters."
    />

    <Card v-else>
      <CardContent class="pt-6">
        <Table>
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
