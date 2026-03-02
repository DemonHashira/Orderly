<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'

const router = useRouter()
</script>

<template>
  <main
    class="mx-auto flex min-h-svh w-full max-w-3xl flex-col items-center justify-center gap-4 px-6 text-center"
  >
    <h1 class="text-3xl font-bold">Forbidden</h1>
    <p class="text-muted-foreground">You do not have permission to access this page.</p>
    <Button @click="router.push('/dashboard')">Back to dashboard</Button>
  </main>
</template>
