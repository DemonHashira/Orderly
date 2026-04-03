import { describe, expect, it } from 'vitest'
import { buildSuggestedProductSku } from '@/features/products/sku'

describe('buildSuggestedProductSku', () => {
  it('builds an uppercase sku from a product name', () => {
    expect(buildSuggestedProductSku('Winter Jacket')).toBe('WINTER-JACKET')
  })

  it('removes extra whitespace and punctuation', () => {
    expect(buildSuggestedProductSku('  Winter!   Jacket 2026  ')).toBe('WINTER-JACKET-2026')
  })

  it('collapses repeated separators', () => {
    expect(buildSuggestedProductSku('Winter --- Jacket___Drop')).toBe('WINTER-JACKET-DROP')
  })

  it('returns an empty string for blank or unsupported values', () => {
    expect(buildSuggestedProductSku('   ')).toBe('')
    expect(buildSuggestedProductSku('!!!')).toBe('')
  })
})
