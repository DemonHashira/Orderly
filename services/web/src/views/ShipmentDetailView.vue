<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { formatDateTime } from '@/lib/formatters'
import { useShipmentQuery } from '@/features/shipments/composables/useShipmentsQueries'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'

const route = useRoute()
const shipmentId = computed(() => Number(route.params.id))

const shipmentQuery = useShipmentQuery(shipmentId)
const shipment = computed(() => shipmentQuery.data.value?.data)
const isInitialLoading = useInitialLoadingGate(shipmentQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && shipmentQuery.isFetching.value)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Shipment Detail"
      description="Shipment metadata and current order outcome."
    />

    <ApiErrorAlert v-if="shipmentQuery.error.value" message="Unable to load shipment." />

    <Card v-else-if="shipment">
      <CardHeader>
        <CardTitle>{{ shipment.order?.reference ?? `Shipment #${shipment.id}` }}</CardTitle>
        <CardDescription>Courier and outcome timeline</CardDescription>
      </CardHeader>
      <CardContent class="space-y-2 text-sm">
        <p><span class="font-medium">Courier:</span> {{ shipment.courier }}</p>
        <p>
          <span class="font-medium">Tracking number:</span> {{ shipment.tracking_number ?? '-' }}
        </p>
        <p>
          <span class="font-medium">Shipped at:</span> {{ formatDateTime(shipment.shipped_at) }}
        </p>
        <p>
          <span class="font-medium">Delivered at:</span> {{ formatDateTime(shipment.delivered_at) }}
        </p>
        <p>
          <span class="font-medium">Order status:</span>
          <StatusBadge :status="shipment.order?.current_status ?? 'shipped'" />
        </p>
      </CardContent>
    </Card>
  </section>
</template>
