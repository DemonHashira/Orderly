export type CreateShipmentPayload = {
  courier: string
  tracking_number?: string
  shipped_at?: string
}

export type CreateShipmentMutationPayload = {
  orderId: number
  payload: CreateShipmentPayload
}
