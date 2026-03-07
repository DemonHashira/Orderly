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
import {
  useReturnsQuery,
  useRestockReturnMutation,
} from '@/features/returns/composables/useReturnsQueries'
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
} from '@/shared/ui'
import { BASIC_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'returns' as const
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

const returnsQuery = useReturnsQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    reason: debouncedSearch.value,
  })),
)

const restockMutation = useRestockReturnMutation()

const returnOrders = computed(() => returnsQuery.data.value?.data ?? [])
const meta = computed(() => returnsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(returnsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && returnsQuery.isFetching.value)

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
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Returns" description="Manage return records and restock decisions." />

    <Card>
      <CardContent class="pt-6">
        <DebouncedSearchInput v-model="search" placeholder="Search by reason" />
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="returnsQuery.error.value" message="Failed to load returns." />

    <EmptyStateCard
      v-if="!returnsQuery.isLoading.value && returnOrders.length === 0"
      title="No returns"
      description="No return records available for the current filters."
    />

    <Card v-else>
      <CardContent class="pt-6">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Order</TableHead>
              <TableHead>Reason</TableHead>
              <TableHead class="text-right">Items</TableHead>
              <TableHead class="text-right">Restock</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="returnOrder in returnOrders" :key="returnOrder.id">
              <TableCell>
                <RouterLink :to="`/returns/${returnOrder.id}`" class="font-medium hover:underline">
                  {{ returnOrder.order?.reference ?? `#${returnOrder.order_id}` }}
                </RouterLink>
              </TableCell>
              <TableCell>{{ returnOrder.reason ?? '-' }}</TableCell>
              <TableCell class="text-right">{{ returnOrder.items?.length ?? 0 }}</TableCell>
              <TableCell class="text-right">
                <ConfirmActionDialog
                  title="Restock return"
                  description="Apply restockable quantities back to inventory."
                  confirm-label="Restock"
                  @confirm="restockMutation.mutate(returnOrder.id)"
                >
                  <template #trigger>
                    <Button size="sm" variant="outline">Restock</Button>
                  </template>
                </ConfirmActionDialog>
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
