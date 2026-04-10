<script setup lang="ts">
import { GalleryVerticalEnd } from 'lucide-vue-next'
import LoginForm from '@/features/auth/ui/LoginForm.vue'
</script>

<template>
  <main class="grid min-h-svh overflow-hidden lg:grid-cols-2">
    <div
      class="animate-in fade-in-0 slide-in-from-left-2 duration-500 flex flex-col gap-4 p-6 md:p-10"
    >
      <div class="flex justify-center gap-2 md:justify-start">
        <span
          class="flex items-center gap-2 text-lg font-semibold transition-transform duration-200 hover:translate-x-0.5"
        >
          <span
            class="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-md transition-all duration-200"
          >
            <GalleryVerticalEnd class="size-4" />
          </span>
          Orderly
        </span>
      </div>

      <div class="flex flex-1 items-center justify-center">
        <div class="w-full max-w-sm">
          <LoginForm />
        </div>
      </div>
    </div>

    <div
      class="bg-primary/5 text-primary animate-in fade-in-0 slide-in-from-right-2 duration-700 hidden lg:flex lg:flex-col lg:justify-end lg:p-12"
    >
      <blockquote class="max-w-3xl space-y-4 transition-all duration-300">
        <p class="text-2xl leading-tight font-medium">
          "In business, efficiency is paramount. The best systems are those that bring order to
          chaos, transforming complexity into clarity. When you master your processes, you master
          your success."
        </p>
        <footer class="text-base font-semibold">Orderly Philosophy</footer>
      </blockquote>
    </div>
  </main>
</template>
