<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useLogoutMutation } from '@/features/auth/composables/useLogoutMutation'

const { user } = useAuth()
const logoutMutation = useLogoutMutation()
const router = useRouter()

const onLogout = async (): Promise<void> => {
  try {
    await logoutMutation.mutateAsync()
  } finally {
    await router.push('/login')
  }
}
</script>

<template>
  <main class="mx-auto flex min-h-svh w-full max-w-5xl flex-col gap-6 px-6 py-10">
    <header class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Dashboard</h1>
        <p class="text-muted-foreground mt-1">
          Welcome back{{ user?.first_name ? `, ${user.first_name}` : '' }}.
        </p>
      </div>
      <Button variant="outline" @click="onLogout">Logout</Button>
    </header>
  </main>
</template>
