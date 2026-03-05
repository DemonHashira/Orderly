export {}

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    permission?: string
    navGroup?: 'operations' | 'inventory' | 'catalog'
    transition?: string
  }
}
