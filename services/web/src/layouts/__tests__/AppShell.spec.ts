import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { computed } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import AppShell from '../AppShell.vue'

const logoutMutateAsync = vi.fn()

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    permissions: computed(() => ['dashboard.view']),
    roles: computed(() => ['Owner']),
    user: computed(() => ({
      first_name: 'Test',
      email: 'test@example.com',
    })),
  }),
}))

vi.mock('@/features/auth/composables/useLogoutMutation', () => ({
  useLogoutMutation: () => ({
    mutateAsync: logoutMutateAsync,
  }),
}))

vi.mock('@/features/dashboard/model', () => ({
  resolveDashboardVariant: () => 'owner',
  sortByPreferredOrder: (ids: string[]) => ids,
}))

vi.mock('@/features/navigation/nav-items', () => ({
  NAV_GROUP_LABELS: { operations: 'Operations', inventory: 'Inventory', catalog: 'Catalog' },
  NAV_ITEMS: [],
  filterNavByPermissions: () => [],
  findNavLabelByPath: () => 'Dashboard',
  getQuickActionsByPermissions: () => [],
}))

vi.mock('@/app/composables/useRouteTransitionScrollReset', () => ({
  useRouteTransitionScrollReset: () => ({
    onBeforeEnter: vi.fn(),
  }),
}))

describe('AppShell account menu', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    logoutMutateAsync.mockResolvedValue(undefined)
  })

  const mountShell = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/',
          component: AppShell,
          children: [
            { path: 'dashboard', component: { template: '<div>dashboard route</div>' } },
            { path: '', component: { template: '<div>dashboard</div>' } },
            { path: 'account/security', component: { template: '<div>security</div>' } },
            { path: 'login', component: { template: '<div>login</div>' } },
          ],
        },
      ],
    })
    await router.replace('/')

    const wrapper = mount(AppShell, {
      global: {
        plugins: [createPinia(), router],
        stubs: {
          SidebarProvider: { template: '<div><slot /></div>' },
          Sidebar: { template: '<div><slot /></div>' },
          SidebarHeader: { template: '<div><slot /></div>' },
          SidebarContent: { template: '<div><slot /></div>' },
          SidebarFooter: { template: '<div><slot /></div>' },
          SidebarGroup: { template: '<div><slot /></div>' },
          SidebarGroupContent: { template: '<div><slot /></div>' },
          SidebarGroupLabel: { template: '<div><slot /></div>' },
          SidebarMenu: { template: '<div><slot /></div>' },
          SidebarMenuItem: { template: '<div><slot /></div>' },
          SidebarMenuButton: { template: '<div><slot /></div>' },
          SidebarRail: { template: '<div />' },
          SidebarInset: { template: '<main data-test="sidebar-inset"><slot /></main>' },
          SidebarTrigger: { template: '<button><slot /></button>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuLabel: { template: '<div><slot /></div>' },
          DropdownMenuSeparator: { template: '<div />' },
          DropdownMenuItem: {
            emits: ['click'],
            template: '<button @click="$emit(\'click\')"><slot /></button>',
          },
          Sheet: { template: '<div><slot /></div>' },
          SheetTrigger: { template: '<div><slot /></div>' },
          SheetContent: { template: '<div><slot /></div>' },
          SheetHeader: { template: '<div><slot /></div>' },
          SheetTitle: { template: '<div><slot /></div>' },
          Breadcrumb: { template: '<div><slot /></div>' },
          BreadcrumbItem: { template: '<div><slot /></div>' },
          BreadcrumbList: { template: '<div><slot /></div>' },
          BreadcrumbPage: { template: '<div><slot /></div>' },
          BreadcrumbSeparator: { template: '<div />' },
          Separator: { template: '<div />' },
          Button: { template: '<button><slot /></button>' },
          Package2: { template: '<i />' },
          Menu: { template: '<i />' },
          KeyRound: { template: '<i />' },
          LogOut: { template: '<i />' },
        },
      },
    })

    return { wrapper, router }
  }

  it('navigates to account security from account menu', async () => {
    const { wrapper, router } = await mountShell()
    const pushSpy = vi.spyOn(router, 'push')

    const changePasswordItem = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Change password'))
    expect(changePasswordItem).toBeDefined()

    await changePasswordItem!.trigger('click')

    expect(pushSpy).toHaveBeenCalledWith('/account/security')
  })

  it('keeps logout action wired', async () => {
    const { wrapper, router } = await mountShell()
    const pushSpy = vi.spyOn(router, 'push')

    const logoutItem = wrapper.findAll('button').find((button) => button.text().includes('Logout'))
    expect(logoutItem).toBeDefined()

    await logoutItem!.trigger('click')

    expect(logoutMutateAsync).toHaveBeenCalledTimes(1)
    expect(pushSpy).toHaveBeenCalledWith('/login')
  })

  it('uses a single main landmark and exposes primary navigation', async () => {
    const { wrapper } = await mountShell()
    const shellMain = wrapper.get('[data-test="sidebar-inset"]').element
    const nestedMainChildren = Array.from(shellMain.children).filter(
      (element) => element.tagName.toLowerCase() === 'main',
    )

    expect(nestedMainChildren).toHaveLength(0)
    expect(wrapper.find('nav[aria-label="Primary"]').exists()).toBe(true)
  })
})
