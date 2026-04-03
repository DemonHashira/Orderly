import { z } from 'zod'
import { type CustomerDialogForm, type CustomerFormField } from '@/features/customers/types'

const customerPhonePattern = /^[0-9+\-\s()]+$/

const mapFirstFieldErrors = (issues: z.ZodIssue[]) => {
  const nextErrors: Record<string, string> = {}

  issues.forEach((issue) => {
    const field = issue.path.map((segment) => String(segment)).join('.')
    if (!field || nextErrors[field]) {
      return
    }

    nextErrors[field] = issue.message
  })

  return nextErrors
}

const countPhoneDigits = (value: string): number => value.replace(/\D/g, '').length

export const buildCustomerDialogSchema = () =>
  z.object({
    first_name: z
      .string()
      .trim()
      .min(1, 'First name is required.')
      .max(255, 'First name must be 255 characters or fewer.'),
    middle_name: z.string().trim().max(255, 'Middle name must be 255 characters or fewer.'),
    last_name: z
      .string()
      .trim()
      .min(1, 'Last name is required.')
      .max(255, 'Last name must be 255 characters or fewer.'),
    phone: z
      .string()
      .trim()
      .min(1, 'Phone is required.')
      .max(30, 'Phone must be 30 characters or fewer.')
      .refine(
        (value) => value.length === 0 || customerPhonePattern.test(value),
        'Phone may only contain digits, spaces, plus signs, hyphens, and parentheses.',
      )
      .refine(
        (value) => value.length === 0 || countPhoneDigits(value) >= 7,
        'Phone must contain at least 7 digits.',
      ),
    email: z
      .string()
      .trim()
      .min(1, 'Email is required.')
      .max(255, 'Email must be 255 characters or fewer.')
      .email('Email must be a valid email address.'),
    address: z.object({
      country: z
        .string()
        .trim()
        .min(1, 'Country is required.')
        .max(255, 'Country must be 255 characters or fewer.'),
      city: z
        .string()
        .trim()
        .min(1, 'City is required.')
        .max(255, 'City must be 255 characters or fewer.'),
      postal_code: z
        .string()
        .trim()
        .min(1, 'Postal code is required.')
        .max(255, 'Postal code must be 255 characters or fewer.'),
      address_line1: z
        .string()
        .trim()
        .min(1, 'Address line 1 is required.')
        .max(255, 'Address line 1 must be 255 characters or fewer.'),
      address_line2: z.string().trim().max(255, 'Address line 2 must be 255 characters or fewer.'),
    }),
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
