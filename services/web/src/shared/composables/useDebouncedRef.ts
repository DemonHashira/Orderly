import { ref, watch, type Ref } from 'vue'

export const useDebouncedRef = <T>(source: Ref<T>, delay = 300) => {
  const debounced = ref(source.value) as Ref<T>

  let timeoutId: ReturnType<typeof setTimeout> | null = null

  watch(
    source,
    (value) => {
      if (timeoutId) {
        clearTimeout(timeoutId)
      }

      timeoutId = setTimeout(() => {
        debounced.value = value
      }, delay)
    },
    { immediate: true },
  )

  return debounced
}
