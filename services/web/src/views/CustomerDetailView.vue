<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useCustomerQuery } from '@/features/customers/composables/useCustomersQueries'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import { ApiErrorAlert, PageHeader, PageInitialSkeleton, PageRefetchOverlay } from '@/shared/ui'

const route = useRoute()
const customerId = computed(() => Number(route.params.id))

const customerQuery = useCustomerQuery(customerId)
const customer = computed(() => customerQuery.data.value?.data)
const isInitialLoading = useInitialLoadingGate(customerQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && customerQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Customer Detail" description="Customer profile snapshot and contact data." />

    <ApiErrorAlert v-if="customerQuery.error.value" message="Unable to load customer." />

    <Card v-else-if="customer">
      <CardHeader>
        <CardTitle>{{ customer.name || `Customer #${customer.id}` }}</CardTitle>
        <CardDescription>Customer record</CardDescription>
      </CardHeader>
      <CardContent class="space-y-2 text-sm">
        <p><span class="font-medium">First name:</span> {{ customer.first_name ?? '-' }}</p>
        <p><span class="font-medium">Middle name:</span> {{ customer.middle_name ?? '-' }}</p>
        <p><span class="font-medium">Last name:</span> {{ customer.last_name ?? '-' }}</p>
        <p><span class="font-medium">Email:</span> {{ customer.email ?? '-' }}</p>
        <p><span class="font-medium">Phone:</span> {{ customer.phone ?? '-' }}</p>
      </CardContent>
    </Card>
  </section>
</template>
