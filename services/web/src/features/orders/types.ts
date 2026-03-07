export type OrderUpsertItemInput = {
  product_id: number
  quantity: number
  unit_price?: string | null
}

export type OrderUpsertPayload = {
  customer_id: number
  sales_channel_id: number
  internal_notes?: string | null
  items: OrderUpsertItemInput[]
}

export const ORDER_STATUS_OPTIONS = [
  'draft',
  'confirmed',
  'ready_to_ship',
  'shipped',
  'delivered',
  'returned',
  'unpaid',
  'cancelled',
] as const
