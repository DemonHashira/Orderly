import type { Component } from 'vue'

export type NavGroup = 'operations' | 'inventory' | 'catalog'

export type NavItem = {
  id: string
  label: string
  to: string
  icon: Component
  requiredPermission?: string
  group: NavGroup
}
