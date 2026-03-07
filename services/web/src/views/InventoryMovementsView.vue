<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  useCreateInventoryMovementMutation,
  useInventoryMovementsQuery,
} from '@/features/inventory/composables/useInventoryQueries'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  DebouncedSearchInput,
  EmptyStateCard,
  PageInitialSkeleton,
  PageHeader,
  PageRefetchOverlay,
  ServerPagination,
  StatusBadge,
} from '@/shared/ui'
import { formatDateTime } from '@/lib/formatters'
import { BASIC_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'inventory_movements' as const
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

const movementForm = ref({
  product_id: '',
  type: 'adjustment' as 'adjustment' | 'damage' | 'restock',
  quantity_delta: '',
  reason: '',
})

const movementsQuery = useInventoryMovementsQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    q: debouncedSearch.value,
  })),
)

const createMovementMutation = useCreateInventoryMovementMutation()

const movements = computed(() => movementsQuery.data.value?.data ?? [])
const meta = computed(() => movementsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(movementsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && movementsQuery.isFetching.value)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>
    if (!listUiStore.hasRelevantQuery(normalizedQuery, BASIC_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, BASIC_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
      }
      return
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

const submitMovement = async () => {
  await createMovementMutation.mutateAsync({
    product_id: Number(movementForm.value.product_id),
    type: movementForm.value.type,
    quantity_delta: Number(movementForm.value.quantity_delta),
    reason: movementForm.value.reason,
  })

  movementForm.value = {
    product_id: '',
    type: 'adjustment',
    quantity_delta: '',
    reason: '',
  }
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Inventory Movements"
      description="Track movement history and create manual adjustments."
    />

    <Card>
      <CardHeader>
        <CardTitle>Manual Movement</CardTitle>
        <CardDescription>Create adjustment, damage, or restock movement.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="grid gap-3 md:grid-cols-4" @submit.prevent="submitMovement">
          <div class="space-y-1">
            <Label for="product-id">Product ID</Label>
            <Input id="product-id" v-model="movementForm.product_id" required />
          </div>
          <div class="space-y-1">
            <Label for="movement-type">Type</Label>
            <Input id="movement-type" v-model="movementForm.type" required />
          </div>
          <div class="space-y-1">
            <Label for="quantity-delta">Quantity Delta</Label>
            <Input
              id="quantity-delta"
              v-model="movementForm.quantity_delta"
              required
              type="number"
            />
          </div>
          <div class="space-y-1">
            <Label for="reason">Reason</Label>
            <Input id="reason" v-model="movementForm.reason" required />
          </div>
          <div class="md:col-span-4">
            <Button type="submit" :disabled="createMovementMutation.isPending.value"
              >Create movement</Button
            >
          </div>
        </form>
      </CardContent>
    </Card>

    <Card>
      <CardContent class="pt-6">
        <DebouncedSearchInput v-model="search" placeholder="Search movement by product" />
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="movementsQuery.error.value" message="Failed to load movements." />

    <EmptyStateCard
      v-if="!movementsQuery.isLoading.value && movements.length === 0"
      title="No movements"
      description="No movement history for current filters."
    />

    <Card v-else>
      <CardContent class="pt-6">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>Type</TableHead>
              <TableHead class="text-right">Delta</TableHead>
              <TableHead>Created</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="movement in movements" :key="movement.id">
              <TableCell>{{ movement.product.name }}</TableCell>
              <TableCell><StatusBadge :status="movement.type" /></TableCell>
              <TableCell class="text-right">{{ movement.quantity_delta }}</TableCell>
              <TableCell>{{ formatDateTime(movement.created_at) }}</TableCell>
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
