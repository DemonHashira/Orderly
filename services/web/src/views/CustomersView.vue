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
import { useCustomersQuery } from '@/features/customers/composables/useCustomersQueries'
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
} from '@/shared/ui'
import { BASIC_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'customers' as const
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

const customersQuery = useCustomersQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    q: debouncedSearch.value,
  })),
)

const customers = computed(() => customersQuery.data.value?.data ?? [])
const meta = computed(() => customersQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(customersQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && customersQuery.isFetching.value)

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
    <PageHeader title="Customers" description="Search and manage customer profiles." />

    <Card>
      <CardContent class="pt-6">
        <DebouncedSearchInput v-model="search" placeholder="Search by name, email, or phone" />
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="customersQuery.error.value" message="Failed to load customers." />

    <EmptyStateCard
      v-if="!customersQuery.isLoading.value && customers.length === 0"
      title="No customers"
      description="No customer records match your filters."
    />

    <Card v-else>
      <CardContent class="pt-6">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Phone</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="customer in customers" :key="customer.id">
              <TableCell>
                <RouterLink :to="`/customers/${customer.id}`" class="font-medium hover:underline">
                  {{ customer.name || `Customer #${customer.id}` }}
                </RouterLink>
              </TableCell>
              <TableCell>{{ customer.email ?? '-' }}</TableCell>
              <TableCell>{{ customer.phone ?? '-' }}</TableCell>
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
