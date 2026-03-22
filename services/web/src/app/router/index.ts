import { createRouter, createWebHistory } from 'vue-router'
import { queryClient } from '@/lib/query-client'
import { authKeys } from '@/lib/query-keys'
import { fetchMe } from '@/features/auth/api/auth.api'
import { normalizeApiError } from '@/shared/api/errors'
import AppShell from '@/layouts/AppShell.vue'
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import OrdersView from '@/views/OrdersView.vue'
import ShipmentsView from '@/views/ShipmentsView.vue'
import ReturnsView from '@/views/ReturnsView.vue'
import ReturnByOrderView from '@/views/ReturnByOrderView.vue'
import InventoryStocksView from '@/views/InventoryStocksView.vue'
import InventoryMovementsView from '@/views/InventoryMovementsView.vue'
import ReportsOrdersView from '@/views/ReportsOrdersView.vue'
import ReportsInventoryView from '@/views/ReportsInventoryView.vue'
import ReportsReturnsView from '@/views/ReportsReturnsView.vue'
import ProductsView from '@/views/ProductsView.vue'
import CustomersView from '@/views/CustomersView.vue'
import TeamManagementView from '@/views/TeamManagementView.vue'
import ForbiddenView from '@/views/ForbiddenView.vue'
import AccountSecurityView from '@/views/AccountSecurityView.vue'
import '@/app/router/route-meta'

const FIVE_MINUTES = 5 * 60 * 1000

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/',
      component: AppShell,
      meta: {
        requiresAuth: true,
      },
      children: [
        {
          path: '',
          redirect: '/dashboard',
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView,
          meta: { permission: 'dashboard.view' },
        },
        {
          path: 'account/security',
          name: 'account-security',
          component: AccountSecurityView,
        },
        {
          path: 'orders',
          name: 'orders',
          component: OrdersView,
          meta: { permission: 'orders.view', viewKey: 'orders' },
        },
        {
          path: 'reports/orders',
          name: 'reports-orders',
          component: ReportsOrdersView,
          meta: { permission: 'reports.orders.view' },
        },
        {
          path: 'reports/inventory',
          name: 'reports-inventory',
          component: ReportsInventoryView,
          meta: { permission: 'reports.inventory.view' },
        },
        {
          path: 'reports/returns',
          name: 'reports-returns',
          component: ReportsReturnsView,
          meta: { permission: 'reports.returns.view' },
        },
        {
          path: 'orders/new',
          name: 'order-create',
          component: OrdersView,
          meta: { permission: 'orders.create', viewKey: 'orders' },
        },
        {
          path: 'orders/:id',
          name: 'order-detail',
          component: OrdersView,
          meta: { permission: 'orders.view', viewKey: 'orders' },
        },
        {
          path: 'orders/:id/edit',
          name: 'order-edit',
          component: OrdersView,
          meta: { permission: 'orders.update', viewKey: 'orders' },
        },
        {
          path: 'shipments',
          name: 'shipments',
          component: ShipmentsView,
          meta: { permission: 'shipments.view', viewKey: 'shipments' },
        },
        {
          path: 'shipments/:id',
          name: 'shipment-detail',
          component: ShipmentsView,
          meta: { permission: 'shipments.view', viewKey: 'shipments' },
        },
        {
          path: 'returns',
          name: 'returns',
          component: ReturnsView,
          meta: { permission: 'returns.view', viewKey: 'returns' },
        },
        {
          path: 'returns/:id',
          name: 'return-detail',
          component: ReturnsView,
          meta: { permission: 'returns.view', viewKey: 'returns' },
        },
        {
          path: 'orders/:id/return',
          name: 'return-by-order',
          component: ReturnByOrderView,
          meta: { permission: 'returns.view' },
        },
        {
          path: 'inventory/stocks',
          name: 'inventory-stocks',
          component: InventoryStocksView,
          meta: { permission: 'inventory.view' },
        },
        {
          path: 'inventory/movements',
          name: 'inventory-movements',
          component: InventoryMovementsView,
          meta: { permission: 'inventory.view' },
        },
        {
          path: 'products',
          name: 'products',
          component: ProductsView,
          meta: { permission: 'products.view', viewKey: 'products' },
        },
        {
          path: 'products/new',
          name: 'product-create',
          component: ProductsView,
          meta: { permission: 'products.manage', viewKey: 'products' },
        },
        {
          path: 'products/:id',
          name: 'product-detail',
          component: ProductsView,
          meta: { permission: 'products.view', viewKey: 'products' },
        },
        {
          path: 'products/:id/edit',
          name: 'product-edit',
          component: ProductsView,
          meta: { permission: 'products.manage', viewKey: 'products' },
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomersView,
          meta: { permission: 'customers.view', viewKey: 'customers' },
        },
        {
          path: 'customers/new',
          name: 'customer-create',
          component: CustomersView,
          meta: { permission: 'customers.create', viewKey: 'customers' },
        },
        {
          path: 'customers/:id',
          name: 'customer-detail',
          component: CustomersView,
          meta: { permission: 'customers.view', viewKey: 'customers' },
        },
        {
          path: 'customers/:id/edit',
          name: 'customer-edit',
          component: CustomersView,
          meta: { permission: 'customers.update', viewKey: 'customers' },
        },
        {
          path: 'team',
          name: 'team-management',
          component: TeamManagementView,
          meta: { permission: 'users.manage' },
        },
      ],
    },
    {
      path: '/forbidden',
      name: 'forbidden',
      component: ForbiddenView,
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/login',
    },
  ],
})

router.beforeEach(async (to) => {
  if (to.name === 'login') {
    const cachedAuth = queryClient.getQueryData<Awaited<ReturnType<typeof fetchMe>>>(authKeys.me())
    if (cachedAuth?.user) {
      return '/dashboard'
    }

    return true
  }

  if (!to.meta.requiresAuth && !to.meta.permission) {
    return true
  }

  let data: Awaited<ReturnType<typeof fetchMe>> | undefined
  const cachedAuth = queryClient.getQueryData<Awaited<ReturnType<typeof fetchMe>>>(authKeys.me())

  try {
    data = await queryClient.ensureQueryData({
      queryKey: authKeys.me(),
      queryFn: fetchMe,
      staleTime: cachedAuth?.user ? FIVE_MINUTES : 0,
      retry: false,
    })
  } catch (error: unknown) {
    const normalizedError = normalizeApiError(error)

    if (normalizedError.status === 401 || normalizedError.status === 419) {
      queryClient.removeQueries({ queryKey: authKeys.me() })
      if (to.meta.requiresAuth) {
        return { path: '/login', query: { redirect: to.fullPath } }
      }
      return true
    }

    return true
  }

  const isAuthenticated = data?.user != null

  if (to.meta.requiresAuth && !isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  const permission = typeof to.meta.permission === 'string' ? to.meta.permission : null
  if (permission && !data?.permissions?.includes(permission)) {
    return '/forbidden'
  }

  return true
})

export default router
