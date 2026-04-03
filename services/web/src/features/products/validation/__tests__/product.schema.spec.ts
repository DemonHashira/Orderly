import { describe, expect, it } from 'vitest'
import { createEmptyProductDialogForm } from '@/features/products/types'
import {
  validateProductDialogField,
  validateProductDialogForm,
} from '@/features/products/validation/product.schema'

describe('product dialog schema', () => {
  it('requires trimmed sku, name, and sale price fields', () => {
    const form = {
      ...createEmptyProductDialogForm(),
      sku: '   ',
      name: '   ',
      sale_price: '   ',
    }

    const errors = validateProductDialogForm(form)

    expect(errors.sku).toBe('SKU is required.')
    expect(errors.name).toBe('Name is required.')
    expect(errors.sale_price).toBe('Sale price is required.')
  })

  it('enforces sku and name length limits', () => {
    const form = {
      ...createEmptyProductDialogForm(),
      sku: 'S'.repeat(256),
      name: 'N'.repeat(256),
      sale_price: '12.50',
    }

    const errors = validateProductDialogForm(form)

    expect(errors.sku).toBe('SKU must be 255 characters or fewer.')
    expect(errors.name).toBe('Name must be 255 characters or fewer.')
  })

  it('rejects sale price values that are not numeric', () => {
    const form = {
      ...createEmptyProductDialogForm(),
      sku: 'JKT-301',
      name: 'Winter Jacket',
      sale_price: 'abc',
    }

    expect(validateProductDialogField(form, 'sale_price')).toBe(
      'Sale price must be a valid number.',
    )
  })

  it('rejects sale price values below zero', () => {
    const form = {
      ...createEmptyProductDialogForm(),
      sku: 'JKT-301',
      name: 'Winter Jacket',
      sale_price: '-1',
    }

    expect(validateProductDialogField(form, 'sale_price')).toBe(
      'Sale price must be greater than or equal to 0.',
    )
  })

  it('accepts a valid product form', () => {
    const form = {
      ...createEmptyProductDialogForm(),
      sku: 'JKT-301',
      name: 'Winter Jacket',
      sale_price: '99.00',
      description: 'Quilted winter jacket',
      is_active: true,
    }

    expect(validateProductDialogForm(form)).toEqual({})
  })
})
