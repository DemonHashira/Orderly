import { z } from 'zod'
import type {
  TeamMemberDialogForm,
  TeamMemberDialogMode,
  UserFormField,
} from '@/features/admin-users/types'

type ValidationOptions = {
  mode: TeamMemberDialogMode
  canManageRoles: boolean
}

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

export const buildTeamMemberDialogSchema = ({ mode, canManageRoles }: ValidationOptions) =>
  z
    .object({
      first_name: z.string().trim().min(1, 'First name is required.'),
      middle_name: z.string().optional(),
      last_name: z.string().trim().min(1, 'Last name is required.'),
      email: z.string().trim().min(1, 'Email is required.').email('Enter a valid email address.'),
      password: z.string(),
      password_confirmation: z.string(),
      role: z.string(),
      is_active: z.boolean(),
    })
    .superRefine((value, ctx) => {
      if (canManageRoles && value.role.trim().length === 0) {
        ctx.addIssue({
          code: 'custom',
          message: 'Role is required.',
          path: ['role'],
        })
      }

      const shouldValidatePassword =
        mode === 'create' ||
        value.password.trim().length > 0 ||
        value.password_confirmation.trim().length > 0

      if (!shouldValidatePassword) {
        return
      }

      if (value.password.length < 10) {
        ctx.addIssue({
          code: 'custom',
          message: 'Password must be 10+ chars with upper/lowercase, number, and symbol.',
          path: ['password'],
        })
      }

      const hasStrongPassword =
        /[a-z]/.test(value.password) &&
        /[A-Z]/.test(value.password) &&
        /\d/.test(value.password) &&
        /[^A-Za-z0-9]/.test(value.password)

      if (!hasStrongPassword) {
        ctx.addIssue({
          code: 'custom',
          message: 'Password must be 10+ chars with upper/lowercase, number, and symbol.',
          path: ['password'],
        })
      }

      if (value.password_confirmation.length === 0) {
        ctx.addIssue({
          code: 'custom',
          message: 'Password confirmation is required.',
          path: ['password_confirmation'],
        })
      }

      if (value.password_confirmation !== value.password) {
        ctx.addIssue({
          code: 'custom',
          message: 'Password confirmation does not match.',
          path: ['password_confirmation'],
        })
      }
    })

export const validateTeamMemberDialogForm = (
  form: TeamMemberDialogForm,
  options: ValidationOptions,
): Record<string, string> => {
  const parsed = buildTeamMemberDialogSchema(options).safeParse(form)

  if (parsed.success) {
    return {}
  }

  return mapFirstFieldErrors(parsed.error.issues)
}

export const validateTeamMemberDialogField = (
  form: TeamMemberDialogForm,
  field: UserFormField,
  options: ValidationOptions,
): string | null => {
  const fieldErrors = validateTeamMemberDialogForm(form, options)
  return fieldErrors[field] ?? null
}
