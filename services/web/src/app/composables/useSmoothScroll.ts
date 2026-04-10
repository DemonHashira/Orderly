import { useLenis } from 'lenis/vue'
import { onBeforeUnmount, onMounted, watch } from 'vue'

const ROOT_LOCKED_OVERFLOW_VALUES = new Set(['clip', 'hidden'])

export const useSmoothScroll = () => {
  const lenis = useLenis()
  let rootObserver: MutationObserver | null = null
  let isRootLocked: boolean | null = null

  const syncRootScrollLock = () => {
    if (typeof document === 'undefined') {
      return
    }

    const nextLocked = ROOT_LOCKED_OVERFLOW_VALUES.has(document.documentElement.style.overflow)
    const lenisInstance = lenis.value

    if (isRootLocked == null) {
      isRootLocked = nextLocked
      if (nextLocked && lenisInstance != null) {
        lenisInstance.stop()
      }
      return
    }

    if (nextLocked === isRootLocked) {
      return
    }

    isRootLocked = nextLocked

    if (nextLocked) {
      lenisInstance?.stop()
      return
    }

    lenisInstance?.start()
  }

  const destroyObservers = () => {
    rootObserver?.disconnect()
    rootObserver = null
    isRootLocked = null
  }

  onMounted(() => {
    rootObserver = new MutationObserver(syncRootScrollLock)
    rootObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['style'],
    })

    syncRootScrollLock()
  })

  watch(lenis, () => {
    syncRootScrollLock()
  })

  onBeforeUnmount(() => {
    destroyObservers()
  })
}
