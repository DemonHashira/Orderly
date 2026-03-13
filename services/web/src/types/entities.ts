import type { DateRangeParams, PaginationParams, SearchParams } from './common'

export type Order = {
  id: number
  reference: string
  customer_id: number
  sales_channel_id: number
  created_by: number
  current_status: string
  total_amount: string
  internal_notes: string | null
  created_at: string
  updated_at: string
  items?: OrderItem[]
  status_history?: OrderStatusHistory[]
}

export type OrderItem = {
  id: number
  product_id: number
  quantity: number
  unit_price: string
  total_price: string
}

export type OrderStatusHistory = {
  id: number
  status: string
  changed_by: number | null
  changed_at: string
}

export type Shipment = {
  id: number
  order_id: number
  courier: string
  tracking_number: string | null
  shipped_at: string | null
  delivered_at: string | null
  created_at: string
  updated_at: string
  order?: {
    id: number
    reference: string
    current_status: string
  }
}

export type ReturnOrder = {
  id: number
  order_id: number
  reason: string | null
  returned_at: string | null
  created_at: string
  updated_at: string
  order?: {
    id: number
    reference: string
    current_status: string
    customer_id?: number
    items?: Array<{
      id: number
      product_id: number
      quantity: number
      product?: {
        id: number
        name: string
        sku: string
      }
    }>
  }
  items?: ReturnItem[]
}

export type ReturnItem = {
  id: number
  product_id: number
  quantity: number
  restockable: boolean
  product?: {
    id: number
    name: string
    sku: string
  }
}

export type InventoryStock = {
  product: {
    id: number
    sku: string
    name: string
    is_active: boolean
  }
  qty_on_hand: number
  qty_reserved: number
  available: number
}

export type InventoryMovement = {
  id: number
  product_id: number
  type: string
  quantity_delta: number
  reason: string | null
  created_at: string
  product: {
    id: number
    sku: string
    name: string
  }
}

export type Customer = {
  id: number
  name: string
  first_name: string | null
  middle_name: string | null
  last_name: string | null
  email: string | null
  phone: string | null
}

export type Product = {
  id: number
  sku: string
  name: string
  sale_price: string
  description: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export type ProductImportSummary = {
  total_rows: number
  created: number
  updated: number
  failed: number
  errors: Array<{
    row: number
    message: string
  }>
}

export type ProductExportFormat = 'csv' | 'xlsx'

export type SalesChannel = {
  id: number
  code: string
  name: string
}

export type LookupOrderCreate = {
  sales_channels: SalesChannel[]
  products: Array<Pick<Product, 'id' | 'sku' | 'name' | 'sale_price'>>
}

export type OrderListParams = PaginationParams &
  SearchParams & {
    status?: string
    customer_id?: number
    sales_channel_id?: number
    created_from?: string
    created_to?: string
  }

export type ShipmentListParams = PaginationParams & {
  order_id?: number
  courier?: string
  tracking_number?: string
  shipped_from?: string
  shipped_to?: string
  delivered?: boolean
}

export type ReturnListParams = PaginationParams & {
  order_id?: number
  reason?: string
  returned_from?: string
  returned_to?: string
  has_restockable?: boolean
}

export type InventoryStocksListParams = PaginationParams &
  SearchParams & {
    is_active?: boolean
  }

export type InventoryMovementsListParams = PaginationParams &
  SearchParams &
  DateRangeParams & {
    product_id?: number
    type?: string
  }

export type CustomerListParams = PaginationParams &
  SearchParams & {
    email?: string
    phone?: string
  }

export type ProductListParams = PaginationParams &
  SearchParams & {
    is_active?: boolean
  }

export type AdminUser = {
  id: number
  organization_id: number
  first_name: string | null
  middle_name: string | null
  last_name: string | null
  name: string
  email: string
  is_active: boolean
  role: string | null
  roles: string[]
  created_at: string
  updated_at: string
}

export type AdminRole = {
  name: string
}

export type AdminUsersListParams = PaginationParams & SearchParams
