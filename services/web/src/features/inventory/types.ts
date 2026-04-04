export const INVENTORY_MOVEMENT_TYPE_OPTIONS = ['adjustment', 'damage', 'restock'] as const

export type InventoryMovementType = (typeof INVENTORY_MOVEMENT_TYPE_OPTIONS)[number]

export const INVENTORY_STOCK_CONDITION_OPTIONS = [
  'low_stock',
  'out_of_stock',
  'reserved',
  'available',
] as const

export type InventoryStockCondition = (typeof INVENTORY_STOCK_CONDITION_OPTIONS)[number]

export type InventoryProductOption = {
  id: number
  label: string
  sku: string
}
