<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { LogOut, Menu, Package2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarRail,
  SidebarTrigger,
} from '@/components/ui/sidebar'
import { useAuth } from '@/features/auth/composables/useAuth'
import { resolveDashboardVariant, sortByPreferredOrder } from '@/features/dashboard/model'
import { useLogoutMutation } from '@/features/auth/composables/useLogoutMutation'
import {
  filterNavByPermissions,
  findNavLabelByPath,
  getQuickActionsByPermissions,
  NAV_GROUP_LABELS,
  NAV_ITEMS,
  type NavGroup,
} from '@/features/navigation/nav-items'

const route = useRoute()
const router = useRouter()

const logoutMutation = useLogoutMutation()
const { permissions, roles, user } = useAuth()

const visibleItems = computed(() => filterNavByPermissions(NAV_ITEMS, permissions.value))

const groupedItems = computed(() => {
  const grouped = new Map<NavGroup, typeof visibleItems.value>()

  visibleItems.value.forEach((item) => {
    const existing = grouped.get(item.group) ?? []
    grouped.set(item.group, [...existing, item])
  })

  return grouped
})

const pageTitle = computed(() => findNavLabelByPath(route.path))
const dashboardVariant = computed(() =>
  resolveDashboardVariant({
    permissions: permissions.value,
    roles: roles.value,
  }),
)

const quickActions = computed(() => {
  const actions = getQuickActionsByPermissions(permissions.value)

  const preferredByVariant = {
    owner: ['orders', 'returns', 'inventory'],
    order_manager: ['orders', 'returns', 'inventory'],
    logistics: ['orders', 'returns', 'inventory'],
    inventory: ['inventory', 'returns', 'orders'],
    generic: ['orders', 'returns', 'inventory'],
  } as const

  const preferred = preferredByVariant[dashboardVariant.value]
  const sortedIds = sortByPreferredOrder(
    actions.map((action) => action.id),
    preferred,
  )

  return sortedIds
    .map((id) => actions.find((action) => action.id === id))
    .filter((action): action is NonNullable<typeof action> => action != null)
})

const onLogout = async () => {
  try {
    await logoutMutation.mutateAsync()
  } finally {
    await router.push('/login')
  }
}
</script>

<template>
  <SidebarProvider>
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader class="border-b">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton as-child size="lg">
              <RouterLink to="/dashboard">
                <div
                  class="bg-primary text-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md"
                >
                  <Package2 class="size-4" />
                </div>
                <div class="grid flex-1 text-left text-sm leading-tight">
                  <span class="truncate font-semibold">Orderly</span>
                  <span class="truncate text-xs">Operations Center</span>
                </div>
              </RouterLink>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup v-for="[group, items] in groupedItems" :key="group">
          <SidebarGroupLabel>{{ NAV_GROUP_LABELS[group] }}</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem v-for="item in items" :key="item.id">
                <SidebarMenuButton
                  as-child
                  :is-active="route.path === item.to || route.path.startsWith(`${item.to}/`)"
                >
                  <RouterLink :to="item.to">
                    <component :is="item.icon" />
                    <span>{{ item.label }}</span>
                  </RouterLink>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter class="border-t p-2">
        <SidebarMenu>
          <SidebarMenuItem>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <SidebarMenuButton>
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-muted text-xs font-semibold"
                  >
                    {{ user?.first_name?.[0] ?? user?.email?.[0] ?? 'U' }}
                  </span>
                  <div class="grid flex-1 text-left text-sm leading-tight">
                    <span class="truncate font-medium">
                      {{ user?.first_name ?? 'User' }}
                    </span>
                    <span class="truncate text-xs text-muted-foreground">{{ user?.email }}</span>
                  </div>
                </SidebarMenuButton>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-56">
                <DropdownMenuLabel>My Account</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="onLogout">
                  <LogOut class="mr-2 size-4" />
                  Logout
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>

    <SidebarInset>
      <header class="flex h-14 shrink-0 items-center gap-2 border-b bg-background px-4">
        <SidebarTrigger class="hidden md:inline-flex" />

        <Sheet>
          <SheetTrigger as-child class="md:hidden">
            <Button variant="outline" size="icon">
              <Menu class="size-4" />
            </Button>
          </SheetTrigger>
          <SheetContent side="left" class="w-80 p-0">
            <SheetHeader class="sr-only">
              <SheetTitle>Navigation</SheetTitle>
            </SheetHeader>
            <div class="p-4">
              <nav class="space-y-1">
                <RouterLink
                  v-for="item in visibleItems"
                  :key="item.id"
                  :to="item.to"
                  class="hover:bg-accent hover:text-accent-foreground flex items-center gap-2 rounded-md px-3 py-2 text-sm"
                >
                  <component :is="item.icon" class="size-4" />
                  {{ item.label }}
                </RouterLink>
              </nav>
            </div>
          </SheetContent>
        </Sheet>

        <Separator orientation="vertical" class="mx-1 data-[orientation=vertical]:h-4" />

        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem class="hidden md:block">
              <RouterLink to="/dashboard" class="text-muted-foreground hover:text-foreground">
                Orderly
              </RouterLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator class="hidden md:block" />
            <BreadcrumbItem>
              <BreadcrumbPage>{{ pageTitle }}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div class="ml-auto hidden items-center gap-2 lg:flex">
          <Button
            v-for="action in quickActions"
            :key="action.id"
            as-child
            variant="outline"
            size="sm"
          >
            <RouterLink :to="action.to">
              <component :is="action.icon" class="mr-1 size-4" />
              {{ action.label }}
            </RouterLink>
          </Button>
        </div>
      </header>

      <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <RouterView v-slot="{ Component, route: currentRoute }">
          <Transition :name="currentRoute.meta.transition ?? 'app-page'" mode="out-in" appear>
            <component :is="Component" :key="currentRoute.path" />
          </Transition>
        </RouterView>
      </main>
    </SidebarInset>
  </SidebarProvider>
</template>
