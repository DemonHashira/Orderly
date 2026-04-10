import { describe, expect, it } from 'vitest'
import { isElementOverflowing } from '@/shared/ui/overflow-tooltip'

describe('isElementOverflowing', () => {
  it('returns true when the element overflows horizontally', () => {
    expect(
      isElementOverflowing({
        clientWidth: 120,
        scrollWidth: 180,
      }),
    ).toBe(true)
  })

  it('returns true when the element overflows vertically', () => {
    expect(
      isElementOverflowing({
        clientWidth: 120,
        scrollWidth: 120,
        clientHeight: 20,
        scrollHeight: 40,
      }),
    ).toBe(true)
  })

  it('returns false when the element fits in its box', () => {
    expect(
      isElementOverflowing({
        clientWidth: 180,
        scrollWidth: 180,
        clientHeight: 20,
        scrollHeight: 20,
      }),
    ).toBe(false)
  })
})
