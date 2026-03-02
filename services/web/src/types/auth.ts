export type AuthUser = {
  id: number
  organization_id: number
  email: string
  first_name: string | null
  middle_name: string | null
  last_name: string | null
  is_active: boolean
}

export type AuthMeResponse = {
  user: AuthUser | null
  roles: string[]
  permissions: string[]
}

export type LoginPayload = {
  email: string
  password: string
  remember?: boolean
}
