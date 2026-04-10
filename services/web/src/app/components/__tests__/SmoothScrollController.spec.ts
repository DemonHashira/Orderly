import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SmoothScrollController from '../SmoothScrollController.vue'

const smoothScrollControllerState = vi.hoisted(() => ({
  useSmoothScroll: vi.fn(),
}))

vi.mock('@/app/composables/useSmoothScroll', () => ({
  useSmoothScroll: smoothScrollControllerState.useSmoothScroll,
}))

vi.mock('lenis/vue', () => ({
  VueLenis: {
    name: 'VueLenis',
    template: '<div data-test="vue-lenis" />',
    props: {
      root: { type: Boolean, required: false },
      options: { type: Object, required: false },
      autoRaf: { type: Boolean, required: false },
    },
  },
}))

describe('SmoothScrollController', () => {
  beforeEach(() => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation(() => ({
        matches: false,
        media: '(prefers-reduced-motion: reduce)',
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('mounts the root VueLenis wrapper and wires the router controller', () => {
    const wrapper = mount(SmoothScrollController)

    const vueLenis = wrapper.getComponent({ name: 'VueLenis' })

    expect(smoothScrollControllerState.useSmoothScroll).toHaveBeenCalledTimes(1)
    expect(smoothScrollControllerState.useSmoothScroll).toHaveBeenCalledWith()
    expect(vueLenis.props('root')).toBe(true)
    expect(vueLenis.props('autoRaf')).toBe(true)
    expect(vueLenis.props('options')).toEqual({
      allowNestedScroll: true,
    })
  })

  it('skips the VueLenis wrapper when reduced motion is preferred', () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation(() => ({
        matches: true,
        media: '(prefers-reduced-motion: reduce)',
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    const wrapper = mount(SmoothScrollController)

    expect(wrapper.find('[data-test="vue-lenis"]').exists()).toBe(false)
  })
})
