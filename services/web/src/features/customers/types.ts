export type CustomerUpsertPayload = {
  first_name: string
  middle_name?: string | null
  last_name: string
  phone: string
  email: string
}

export type CustomerDialogMode = 'create' | 'edit'

export type CustomerDialogForm = {
  first_name: string
  middle_name: string
  last_name: string
  phone: string
  email: string
}

export type CustomerFormField = keyof CustomerDialogForm

export const CUSTOMER_FORM_FIELDS_TO_VALIDATE: CustomerFormField[] = [
  'first_name',
  'middle_name',
  'last_name',
  'phone',
  'email',
]

export const createEmptyCustomerDialogForm = (): CustomerDialogForm => ({
  first_name: '',
  middle_name: '',
  last_name: '',
  phone: '',
  email: '',
})
