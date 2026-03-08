export type CreateAdminUserPayload = {
  first_name: string
  middle_name?: string | null
  last_name: string
  email: string
  password: string
  password_confirmation: string
  is_active?: boolean
  role?: string
}

export type UpdateAdminUserPayload = {
  first_name?: string
  middle_name?: string | null
  last_name?: string
  email?: string
  password?: string
  password_confirmation?: string
}

export type UpdateAdminUserStatusPayload = {
  is_active: boolean
}

export type UpdateAdminUserRolePayload = {
  role: string
}

export type UpdateAdminUserMutationPayload = {
  id: number
  payload: UpdateAdminUserPayload
}

export type UpdateAdminUserStatusMutationPayload = {
  id: number
  isActive: boolean
}

export type UpdateAdminUserRoleMutationPayload = {
  id: number
  role: string
}

export type TeamMemberDialogMode = 'create' | 'edit'

export type TeamMemberDialogForm = {
  first_name: string
  middle_name: string
  last_name: string
  email: string
  password: string
  password_confirmation: string
  role: string
  is_active: boolean
}

export type UserFormField =
  | 'first_name'
  | 'middle_name'
  | 'last_name'
  | 'email'
  | 'password'
  | 'password_confirmation'
  | 'role'

export const USER_FORM_FIELDS_TO_VALIDATE: UserFormField[] = [
  'first_name',
  'last_name',
  'email',
  'password',
  'password_confirmation',
  'role',
]

export const createEmptyTeamMemberDialogForm = (defaultRole = ''): TeamMemberDialogForm => ({
  first_name: '',
  middle_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: defaultRole,
  is_active: true,
})
