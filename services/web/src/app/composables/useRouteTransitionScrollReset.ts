import { nextTick } from 'vue'
import { useLenis } from 'lenis/vue'

export const useRouteTransitionScrollReset = () => {
  const lenis = useLenis()

  const onBeforeEnter = async () => {
    await nextTick()
    lenis.value?.scrollTo(0, { immediate: true })
  }

  return {
    onBeforeEnter,
  }
}
