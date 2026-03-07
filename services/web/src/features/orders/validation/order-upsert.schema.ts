import { z } from 'zod'

export const orderUpsertSchema = z.object({
  customer_id: z.number().int().positive('Customer is required'),
  sales_channel_id: z.number().int().positive('Sales channel is required'),
  internal_notes: z.string().optional().nullable(),
  items: z
    .array(
      z.object({
        product_id: z.number().int().positive('Product is required'),
        quantity: z.number().int().min(1, 'Quantity must be at least 1'),
        unit_price: z
          .string()
          .trim()
          .regex(/^\d+(\.\d{1,2})?$/, 'Unit price must be a valid amount')
          .optional()
          .or(z.literal('')),
      }),
    )
    .min(1, 'At least one item is required'),
})
