<script setup lang="ts">
import { computed } from 'vue'
import { useAuth } from '@/features/auth/composables/useAuth'
import { resolveDashboardRoleView } from '@/features/dashboard/model'
import OwnerDashboardView from '@/features/dashboard/views/OwnerDashboardView.vue'
import OrderManagerDashboardView from '@/features/dashboard/views/OrderManagerDashboardView.vue'
import LogisticsDashboardView from '@/features/dashboard/views/LogisticsDashboardView.vue'
import InventoryDashboardView from '@/features/dashboard/views/InventoryDashboardView.vue'
import GenericDashboardView from '@/features/dashboard/views/GenericDashboardView.vue'

const { permissions, roles } = useAuth()

const roleView = computed(() =>
  resolveDashboardRoleView({
    permissions: permissions.value,
    roles: roles.value,
  }),
)

const roleComponent = computed(() => {
  if (roleView.value === 'owner') return OwnerDashboardView
  if (roleView.value === 'order_manager') return OrderManagerDashboardView
  if (roleView.value === 'logistics') return LogisticsDashboardView
  if (roleView.value === 'inventory') return InventoryDashboardView
  return GenericDashboardView
})
</script>

<template>
  <component :is="roleComponent" />
</template>
