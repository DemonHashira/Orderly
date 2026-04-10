import { shallowRef } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useRouteTransitionScrollReset } from '../useRouteTransitionScrollReset'

const routeTransitionScrollResetState = vi.hoisted(() => ({
  instance: undefined as
    | ReturnType<
        typeof shallowRef<
          | {
              scrollTo: ReturnType<typeof vi.fn>
            }
          | undefined
        >
      >
    | undefined,
  scrollTo: vi.fn(),
}))

vi.mock('lenis/vue', () => ({
  useLenis: () => routeTransitionScrollResetState.instance,
}))

describe('useRouteTransitionScrollReset', () => {
  beforeEach(() => {
    routeTransitionScrollResetState.instance = shallowRef({
      scrollTo: routeTransitionScrollResetState.scrollTo,
    })
    routeTransitionScrollResetState.scrollTo.mockClear()
  })

  it('resets scroll when the routed page is about to enter', async () => {
    const { onBeforeEnter } = useRouteTransitionScrollReset()

    await onBeforeEnter()

    expect(routeTransitionScrollResetState.scrollTo).toHaveBeenCalledWith(0, { immediate: true })
  })
})
