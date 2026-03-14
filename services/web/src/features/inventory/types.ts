export const INVENTORY_MOVEMENT_TYPE_OPTIONS = ['adjustment', 'damage', 'restock'] as const

export type InventoryMovementType = (typeof INVENTORY_MOVEMENT_TYPE_OPTIONS)[number]

export type InventoryProductOption = {
  id: number
  label: string
  sku: string
}
