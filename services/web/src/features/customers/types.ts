export const DEFAULT_CUSTOMER_ADDRESS_COUNTRY = 'Bulgaria'

export type CustomerAddressUpsertPayload = {
  country: string
  city: string
  postal_code: string
  address_line1: string
  address_line2?: string | null
}

export type CustomerUpsertPayload = {
  first_name: string
  middle_name?: string | null
  last_name: string
  phone: string
  email: string
  address?: CustomerAddressUpsertPayload | null
}

export type CustomerDialogMode = 'create' | 'edit'

export type CustomerDialogAddressForm = {
  country: string
  city: string
  postal_code: string
  address_line1: string
  address_line2: string
}

export type CustomerDialogForm = {
  first_name: string
  middle_name: string
  last_name: string
  phone: string
  email: string
  address: CustomerDialogAddressForm
}

export type CustomerFormField =
  | 'first_name'
  | 'middle_name'
  | 'last_name'
  | 'phone'
  | 'email'
  | 'address.country'
  | 'address.city'
  | 'address.postal_code'
  | 'address.address_line1'
  | 'address.address_line2'

export const CUSTOMER_FORM_FIELDS_TO_VALIDATE: CustomerFormField[] = [
  'first_name',
  'middle_name',
  'last_name',
  'phone',
  'email',
  'address.country',
  'address.city',
  'address.postal_code',
  'address.address_line1',
  'address.address_line2',
]

export const createEmptyCustomerDialogForm = (): CustomerDialogForm => ({
  first_name: '',
  middle_name: '',
  last_name: '',
  phone: '',
  email: '',
  address: {
    country: DEFAULT_CUSTOMER_ADDRESS_COUNTRY,
    city: '',
    postal_code: '',
    address_line1: '',
    address_line2: '',
  },
})
