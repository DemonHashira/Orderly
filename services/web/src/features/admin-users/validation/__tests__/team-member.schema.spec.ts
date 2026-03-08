import { describe, expect, it } from 'vitest'
import { createEmptyTeamMemberDialogForm } from '@/features/admin-users/types'
import {
  validateTeamMemberDialogField,
  validateTeamMemberDialogForm,
} from '@/features/admin-users/validation/team-member.schema'

describe('team member dialog schema', () => {
  it('requires basic fields and role in create mode', () => {
    const form = createEmptyTeamMemberDialogForm('')

    const errors = validateTeamMemberDialogForm(form, {
      mode: 'create',
      canManageRoles: true,
    })

    expect(errors.first_name).toBeDefined()
    expect(errors.last_name).toBeDefined()
    expect(errors.email).toBeDefined()
    expect(errors.password).toBeDefined()
    expect(errors.password_confirmation).toBeDefined()
    expect(errors.role).toBeDefined()
  })

  it('does not require password in edit mode when not changing it', () => {
    const form = {
      ...createEmptyTeamMemberDialogForm('Owner'),
      first_name: 'Viktor',
      last_name: 'Logodazhki',
      email: 'vlogodazhki@otakustore.test',
      password: '',
      password_confirmation: '',
    }

    const errors = validateTeamMemberDialogForm(form, {
      mode: 'edit',
      canManageRoles: true,
    })

    expect(errors.password).toBeUndefined()
    expect(errors.password_confirmation).toBeUndefined()
  })

  it('validates email and password confirmation per field', () => {
    const form = {
      ...createEmptyTeamMemberDialogForm('Owner'),
      first_name: 'Test',
      last_name: 'User',
      email: 'invalid-email',
      password: 'StrongPass#123',
      password_confirmation: 'StrongPass#124',
    }

    const emailError = validateTeamMemberDialogField(form, 'email', {
      mode: 'create',
      canManageRoles: true,
    })
    const confirmationError = validateTeamMemberDialogField(form, 'password_confirmation', {
      mode: 'create',
      canManageRoles: true,
    })

    expect(emailError).toBe('Enter a valid email address.')
    expect(confirmationError).toBe('Password confirmation does not match.')
  })
})
