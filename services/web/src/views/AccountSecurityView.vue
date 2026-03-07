<script setup lang="ts">
import ChangePasswordForm from '@/features/auth/ui/ChangePasswordForm.vue'
</script>

<template>
  <section class="space-y-5">
    <header class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tight">Account Security</h1>
      <p class="text-muted-foreground text-sm">Update your password to keep your account secure.</p>
    </header>

    <ChangePasswordForm />
  </section>
</template>
