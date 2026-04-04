import { onBeforeUnmount, watch, type Ref } from 'vue'

let lockCount = 0
let initialOverflow = ''
let initialOverscrollBehavior = ''

const applyLock = () => {
  if (typeof document === 'undefined') {
    return
  }

  if (lockCount === 0) {
    initialOverflow = document.documentElement.style.overflow
    initialOverscrollBehavior = document.documentElement.style.overscrollBehavior
  }

  lockCount += 1
  document.documentElement.style.overflow = 'hidden'
  document.documentElement.style.overscrollBehavior = 'none'
}

const releaseLock = () => {
  if (typeof document === 'undefined' || lockCount === 0) {
    return
  }

  lockCount -= 1

  if (lockCount === 0) {
    document.documentElement.style.overflow = initialOverflow
    document.documentElement.style.overscrollBehavior = initialOverscrollBehavior
  }
}

export const useDocumentRootScrollLock = (active: Ref<boolean>) => {
  let isLocked = false

  const syncLock = (shouldLock: boolean) => {
    if (shouldLock === isLocked) {
      return
    }

    if (shouldLock) {
      applyLock()
      isLocked = true
      return
    }

    releaseLock()
    isLocked = false
  }

  watch(
    active,
    (value) => {
      syncLock(value)
    },
    {
      immediate: true,
      flush: 'sync',
    },
  )

  onBeforeUnmount(() => {
    syncLock(false)
  })
}
