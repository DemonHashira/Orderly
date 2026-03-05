<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useReturnByOrderQuery } from '@/features/returns/composables/useReturnsQueries'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import { ApiErrorAlert, PageHeader, PageInitialSkeleton, PageRefetchOverlay } from '@/shared/ui'

const route = useRoute()
const orderId = computed(() => Number(route.params.id))

const returnQuery = useReturnByOrderQuery(orderId)
const returnOrder = computed(() => returnQuery.data.value?.data)
const isInitialLoading = useInitialLoadingGate(returnQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && returnQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader title="Return by Order" description="Fetch linked return for an order." />

    <ApiErrorAlert v-if="returnQuery.error.value" message="No return found for this order." />

    <Card v-else-if="returnOrder">
      <CardHeader>
        <CardTitle>Return #{{ returnOrder.id }}</CardTitle>
        <CardDescription>Order {{ returnOrder.order?.reference ?? orderId }}</CardDescription>
      </CardHeader>
      <CardContent>
        <RouterLink :to="`/returns/${returnOrder.id}`" class="text-primary hover:underline">
          Open return detail
        </RouterLink>
      </CardContent>
    </Card>
  </section>
</template>
