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

const page = ref(1)
const search = ref('')
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

    <Card>
      <CardContent class="pt-6">
        <EmptyStateCard
          v-if="!customersQuery.isLoading.value && customers.length === 0"
          title="No customers"
          description="No customer records match your filters."
        />

        <Table v-else>
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
