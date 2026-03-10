export type AddReturnItemPayload = {
  product_id: number
  quantity: number
  restockable: boolean
}

export type AddReturnItemMutationPayload = {
  id: number
  payload: AddReturnItemPayload
}
