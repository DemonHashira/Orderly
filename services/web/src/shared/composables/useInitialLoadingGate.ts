import { computed, ref, toValue, watchEffect, type MaybeRefOrGetter } from 'vue'

export const useInitialLoadingGate = (isLoading: MaybeRefOrGetter<boolean>) => {
  const isPrimed = ref(false)

  watchEffect(() => {
    if (!toValue(isLoading)) {
      isPrimed.value = true
    }
  })

  return computed(() => !isPrimed.value && toValue(isLoading))
}
