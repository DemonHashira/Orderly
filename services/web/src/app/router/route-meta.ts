export {}

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    permission?: string
    navGroup?: 'operations' | 'reports' | 'inventory' | 'catalog'
    transition?: string
    viewKey?: string
  }
}
