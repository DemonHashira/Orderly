import { z } from 'zod'
import type { CustomerDialogForm, CustomerFormField } from '@/features/customers/types'

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

export const buildCustomerDialogSchema = () =>
  z.object({
    first_name: z.string().trim().min(1, 'First name is required.').max(255),
    middle_name: z.string().trim().max(255, 'Middle name must be 255 characters or fewer.'),
    last_name: z.string().trim().min(1, 'Last name is required.').max(255),
    phone: z
      .string()
      .trim()
      .min(1, 'Phone is required.')
      .max(30, 'Phone must be 30 characters or fewer.'),
    email: z
      .string()
      .trim()
      .min(1, 'Email is required.')
      .max(255)
      .email('Email must be a valid email address.'),
  })

export const validateCustomerDialogForm = (form: CustomerDialogForm): Record<string, string> => {
  const parsed = buildCustomerDialogSchema().safeParse(form)

  if (parsed.success) {
    return {}
  }

  return mapFirstFieldErrors(parsed.error.issues)
}

export const validateCustomerDialogField = (
  form: CustomerDialogForm,
  field: CustomerFormField,
): string | null => {
  const fieldErrors = validateCustomerDialogForm(form)
  return fieldErrors[field] ?? null
}
