import { z } from 'zod'
import type { ProductDialogForm, ProductFormField } from '@/features/products/types'

const mapFirstFieldErrors = (issues: z.ZodIssue[]) => {
  const nextErrors: Record<string, string> = {}

  issues.forEach((issue) => {
    const field = String(issue.path[0] ?? '')
    if (!field || nextErrors[field]) {
      return
    }

    nextErrors[field] = issue.message
  })

  return nextErrors
}

export const buildProductDialogSchema = () =>
  z.object({
    sku: z
      .string()
      .trim()
      .min(1, 'SKU is required.')
      .max(255, 'SKU must be 255 characters or fewer.'),
    name: z
      .string()
      .trim()
      .min(1, 'Name is required.')
      .max(255, 'Name must be 255 characters or fewer.'),
    sale_price: z
      .string()
      .trim()
      .min(1, 'Sale price is required.')
      .refine((value) => Number.isFinite(Number(value)), 'Sale price must be a valid number.')
      .refine((value) => Number(value) >= 0, 'Sale price must be greater than or equal to 0.'),
    description: z.string(),
    is_active: z.boolean(),
  })

export const validateProductDialogForm = (form: ProductDialogForm): Record<string, string> => {
  const parsed = buildProductDialogSchema().safeParse(form)

  if (parsed.success) {
    return {}
  }

  return mapFirstFieldErrors(parsed.error.issues)
}

export const validateProductDialogField = (
  form: ProductDialogForm,
  field: ProductFormField,
): string | null => {
  const fieldErrors = validateProductDialogForm(form)
  return fieldErrors[field] ?? null
}
