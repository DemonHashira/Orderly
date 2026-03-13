import { describe, expect, it, vi } from 'vitest'

const styleState = vi.hoisted(() => ({
  loaded: vi.fn(),
}))

vi.mock('vue-sonner/style.css', () => {
  styleState.loaded()

  return {}
})

vi.mock('vue-sonner', () => ({
  Toaster: {
    name: 'VueSonnerToaster',
    template: '<div data-test="vue-sonner-toaster" />',
  },
}))

describe('Sonner', () => {
  it('imports the base vue-sonner stylesheet', async () => {
    await import('../Sonner.vue')

    expect(styleState.loaded).toHaveBeenCalledTimes(1)
  })
})
