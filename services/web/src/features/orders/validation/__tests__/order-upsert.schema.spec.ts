import { describe, expect, it } from 'vitest'
import { orderUpsertSchema } from '../order-upsert.schema'

describe('orderUpsertSchema', () => {
  it('accepts valid order payload', () => {
    const result = orderUpsertSchema.safeParse({
      customer_id: 1,
      sales_channel_id: 1,
      internal_notes: 'priority',
      items: [
        {
          product_id: 10,
          quantity: 2,
          unit_price: '12.50',
        },
      ],
    })

    expect(result.success).toBe(true)
  })

  it('rejects empty items and invalid price', () => {
    const result = orderUpsertSchema.safeParse({
      customer_id: 1,
      sales_channel_id: 1,
      items: [],
    })

    expect(result.success).toBe(false)
  })
})
