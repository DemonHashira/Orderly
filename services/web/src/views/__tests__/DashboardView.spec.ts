import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DashboardView from '@/views/DashboardView.vue'

const authState = vi.hoisted(() => ({
  roles: [] as string[],
  permissions: [] as string[],
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    roles: { value: authState.roles },
    permissions: { value: authState.permissions },
  }),
}))

vi.mock('@/features/dashboard/views/OwnerDashboardView.vue', () => ({
  default: { template: '<div data-test="owner-dashboard" />' },
}))

vi.mock('@/features/dashboard/views/OrderManagerDashboardView.vue', () => ({
  default: { template: '<div data-test="order-manager-dashboard" />' },
}))

vi.mock('@/features/dashboard/views/LogisticsDashboardView.vue', () => ({
  default: { template: '<div data-test="logistics-dashboard" />' },
}))

vi.mock('@/features/dashboard/views/InventoryDashboardView.vue', () => ({
  default: { template: '<div data-test="inventory-dashboard" />' },
}))

vi.mock('@/features/dashboard/views/GenericDashboardView.vue', () => ({
  default: { template: '<div data-test="generic-dashboard" />' },
}))

describe('DashboardView', () => {
  it('renders owner dashboard from owner-level report permissions', () => {
    authState.roles = ['Owner']
    authState.permissions = [
      'reports.orders.view',
      'reports.inventory.view',
      'reports.returns.view',
    ]
    const wrapper = mount(DashboardView)

    expect(wrapper.find('[data-test="owner-dashboard"]').exists()).toBe(true)
  })

  it('renders order manager dashboard from orders+returns reports without shipment outcomes', () => {
    authState.roles = ['Order Manager']
    authState.permissions = ['reports.orders.view', 'reports.returns.view']
    const wrapper = mount(DashboardView)

    expect(wrapper.find('[data-test="order-manager-dashboard"]').exists()).toBe(true)
  })

  it('renders logistics dashboard from orders+returns reports with shipment outcomes', () => {
    authState.roles = ['Logistics Manager']
    authState.permissions = [
      'reports.orders.view',
      'reports.returns.view',
      'shipments.outcome.returned',
    ]
    const wrapper = mount(DashboardView)

    expect(wrapper.find('[data-test="logistics-dashboard"]').exists()).toBe(true)
  })

  it('renders inventory dashboard from inventory+returns reports', () => {
    authState.roles = ['Inventory Manager']
    authState.permissions = ['reports.inventory.view', 'reports.returns.view']
    const wrapper = mount(DashboardView)

    expect(wrapper.find('[data-test="inventory-dashboard"]').exists()).toBe(true)
  })

  it('falls back to generic dashboard for unknown mixed permissions and role', () => {
    authState.roles = ['Supervisor']
    authState.permissions = ['dashboard.view']
    const wrapper = mount(DashboardView)

    expect(wrapper.find('[data-test="generic-dashboard"]').exists()).toBe(true)
  })

  it('falls back to owner dashboard for mixed permissions when role is Owner', () => {
    authState.roles = ['Owner']
    authState.permissions = ['dashboard.view']
    const wrapper = mount(DashboardView)

    expect(wrapper.find('[data-test="owner-dashboard"]').exists()).toBe(true)
  })
})
