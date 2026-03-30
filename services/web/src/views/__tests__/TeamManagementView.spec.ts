import { computed, ref } from 'vue'
import { createPinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import TeamManagementView from '@/views/TeamManagementView.vue'

const usersData = ref({
  data: [
    {
      id: 1,
      organization_id: 1,
      first_name: 'Owner',
      middle_name: null,
      last_name: 'User',
      name: 'Owner User',
      email: 'owner@example.com',
      is_active: true,
      role: 'Owner',
      roles: ['Owner'],
      created_at: '2026-03-07T00:00:00Z',
      updated_at: '2026-03-07T00:00:00Z',
    },
  ],
  meta: {
    current_page: 1,
    from: 1,
    last_page: 1,
    links: [],
    path: '/api/admin/users',
    per_page: 15,
    to: 1,
    total: 1,
  },
})

const rolesData = ref({
  data: [{ name: 'Owner' }, { name: 'Order Manager' }],
})

const canManageUsers = ref(true)
const canManageRoles = ref(true)

vi.mock('@/features/admin-users/composables/useAdminUsersQueries', () => ({
  useAdminUsersQuery: () => ({
    data: computed(() => usersData.value),
    isLoading: computed(() => false),
    isFetching: computed(() => false),
    error: computed(() => null),
  }),
  useAdminRolesQuery: () => ({
    data: computed(() => rolesData.value),
    isLoading: computed(() => false),
    isFetching: computed(() => false),
    error: computed(() => null),
  }),
  useCreateAdminUserMutation: () => ({
    mutateAsync: vi.fn(),
    isPending: computed(() => false),
  }),
  useUpdateAdminUserMutation: () => ({
    mutateAsync: vi.fn(),
    isPending: computed(() => false),
  }),
  useUpdateAdminUserStatusMutation: () => ({
    mutateAsync: vi.fn(),
    isPending: computed(() => false),
  }),
  useUpdateAdminUserRoleMutation: () => ({
    mutateAsync: vi.fn(),
    isPending: computed(() => false),
  }),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    user: computed(() => ({ id: 1 })),
  }),
}))

vi.mock('@/features/auth/composables/usePermission', () => ({
  usePermission: (permission: string) =>
    computed(() => (permission === 'users.manage' ? canManageUsers.value : canManageRoles.value)),
}))

describe('TeamManagementView', () => {
  beforeEach(() => {
    canManageUsers.value = true
    canManageRoles.value = true
  })

  const mountView = async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/team', component: TeamManagementView }],
    })

    await router.replace('/team')

    return mount(TeamManagementView, {
      global: {
        plugins: [createPinia(), router],
      },
    })
  }

  it('shows role assignment controls when roles.manage permission exists', async () => {
    const wrapper = await mountView()

    expect(wrapper.findAll('[role="combobox"]').length).toBeGreaterThan(0)
    expect(wrapper.find('[data-test="team-open-create"] svg').exists()).toBe(true)
  })

  it('hides role assignment controls when roles.manage permission is missing', async () => {
    canManageRoles.value = false
    const wrapper = await mountView()

    expect(wrapper.findAll('[role="combobox"]').length).toBe(0)
    expect(wrapper.text()).toContain('Owner')
  })

  it('hides the create action when users.manage permission is missing', async () => {
    canManageUsers.value = false
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="team-open-create"]').exists()).toBe(false)
  })
})
