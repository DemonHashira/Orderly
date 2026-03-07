import { z } from 'zod'

export const changePasswordSchema = z
  .object({
    current_password: z.string().min(1, 'Current password is required'),
    password: z
      .string()
      .min(10, 'New password must be at least 10 characters')
      .regex(/[A-Za-z]/, 'New password must include at least one letter')
      .regex(/[a-z]/, 'New password must include at least one lowercase letter')
      .regex(/[A-Z]/, 'New password must include at least one uppercase letter')
      .regex(/[0-9]/, 'New password must include at least one number')
      .regex(/[^A-Za-z0-9]/, 'New password must include at least one symbol'),
    password_confirmation: z.string().min(1, 'Confirm password is required'),
  })
  .refine((value) => value.password === value.password_confirmation, {
    message: 'Password confirmation does not match',
    path: ['password_confirmation'],
  })

export type ChangePasswordSchemaInput = z.input<typeof changePasswordSchema>
