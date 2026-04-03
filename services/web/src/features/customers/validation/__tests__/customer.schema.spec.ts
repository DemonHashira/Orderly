import { describe, expect, it } from 'vitest'
import { createEmptyCustomerDialogForm } from '@/features/customers/types'
import {
  validateCustomerDialogField,
  validateCustomerDialogForm,
} from '@/features/customers/validation/customer.schema'

describe('customer dialog schema', () => {
  it('requires trimmed contact fields', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: '   ',
      middle_name: '   ',
      last_name: '   ',
      phone: '   ',
      email: '   ',
    }

    const errors = validateCustomerDialogForm(form)

    expect(errors.first_name).toBe('First name is required.')
    expect(errors.last_name).toBe('Last name is required.')
    expect(errors.phone).toBe('Phone is required.')
    expect(errors.email).toBe('Email is required.')
    expect(errors.middle_name).toBeUndefined()
  })

  it('accepts valid international-style phone numbers', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: 'Mira',
      middle_name: '',
      last_name: 'Stone',
      phone: '+359 (888) 123-456',
      email: 'mira@example.com',
      address: {
        country: 'Bulgaria',
        city: 'Sofia',
        postal_code: '1000',
        address_line1: 'Tsar Osvoboditel 1',
        address_line2: '',
      },
    }

    expect(validateCustomerDialogForm(form)).toEqual({})
  })

  it('rejects phone numbers with unsupported characters', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: 'Mira',
      last_name: 'Stone',
      phone: '+359 888 123 456#',
      email: 'mira@example.com',
    }

    const phoneError = validateCustomerDialogField(form, 'phone')

    expect(phoneError).toBe(
      'Phone may only contain digits, spaces, plus signs, hyphens, and parentheses.',
    )
  })

  it('rejects phone numbers with fewer than seven digits', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: 'Mira',
      last_name: 'Stone',
      phone: '+12 34',
      email: 'mira@example.com',
    }

    const phoneError = validateCustomerDialogField(form, 'phone')

    expect(phoneError).toBe('Phone must contain at least 7 digits.')
  })

  it('validates email format per field', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: 'Mira',
      last_name: 'Stone',
      phone: '+359 888 123 456',
      email: 'not-an-email',
    }

    expect(validateCustomerDialogField(form, 'email')).toBe('Email must be a valid email address.')
  })

  it('prefills the address country with Bulgaria', () => {
    const form = createEmptyCustomerDialogForm()

    expect(form.address.country).toBe('Bulgaria')
  })

  it('requires the address fields even when only the default country is present', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: 'Mira',
      last_name: 'Stone',
      phone: '+359 888 123 456',
      email: 'mira@example.com',
    }

    const errors = validateCustomerDialogForm(form)

    expect(errors['address.city']).toBe('City is required.')
    expect(errors['address.postal_code']).toBe('Postal code is required.')
    expect(errors['address.address_line1']).toBe('Address line 1 is required.')
  })

  it('requires the rest of the address once address entry has started', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: 'Mira',
      last_name: 'Stone',
      phone: '+359 888 123 456',
      email: 'mira@example.com',
      address: {
        ...createEmptyCustomerDialogForm().address,
        city: 'Sofia',
      },
    }

    const errors = validateCustomerDialogForm(form)

    expect(errors['address.postal_code']).toBe('Postal code is required.')
    expect(errors['address.address_line1']).toBe('Address line 1 is required.')
  })

  it('accepts a complete address', () => {
    const form = {
      ...createEmptyCustomerDialogForm(),
      first_name: 'Mira',
      last_name: 'Stone',
      phone: '+359 888 123 456',
      email: 'mira@example.com',
      address: {
        country: 'Bulgaria',
        city: 'Sofia',
        postal_code: '1000',
        address_line1: 'Tsar Osvoboditel 1',
        address_line2: 'Floor 2',
      },
    }

    expect(validateCustomerDialogForm(form)).toEqual({})
  })
})
