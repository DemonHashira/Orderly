import { mount } from '@vue/test-utils'
import { defineComponent, shallowRef } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useSmoothScroll } from '../useSmoothScroll'

const lenisState = vi.hoisted(() => ({
  instance: undefined as
    | ReturnType<
        typeof shallowRef<
          | {
              scrollTo: ReturnType<typeof vi.fn>
              start: ReturnType<typeof vi.fn>
              stop: ReturnType<typeof vi.fn>
            }
          | undefined
        >
      >
    | undefined,
  scrollTo: vi.fn(),
  start: vi.fn(),
  stop: vi.fn(),
}))

vi.mock('lenis/vue', () => ({
  useLenis: () => lenisState.instance,
}))

describe('useSmoothScroll', () => {
  beforeEach(() => {
    lenisState.instance = shallowRef({
      scrollTo: lenisState.scrollTo,
      start: lenisState.start,
      stop: lenisState.stop,
    })
    lenisState.scrollTo.mockClear()
    lenisState.start.mockClear()
    lenisState.stop.mockClear()

    document.documentElement.style.overflow = ''
    document.documentElement.style.overscrollBehavior = ''
  })

  afterEach(() => {
    document.documentElement.removeAttribute('style')
  })

  it('does not register a router-level scroll reset', () => {
    const Host = defineComponent({
      setup() {
        useSmoothScroll()
        return () => null
      },
    })

    mount(Host)

    expect(lenisState.scrollTo).not.toHaveBeenCalled()
  })

  it('stops and resumes Lenis when document root scrolling is locked and unlocked', async () => {
    const Host = defineComponent({
      setup() {
        useSmoothScroll()
        return () => null
      },
    })

    const wrapper = mount(Host)

    document.documentElement.style.overflow = 'hidden'
    await Promise.resolve()
    expect(lenisState.stop).toHaveBeenCalled()

    document.documentElement.style.overflow = ''
    await Promise.resolve()
    expect(lenisState.start).toHaveBeenCalled()

    wrapper.unmount()
  })
})
