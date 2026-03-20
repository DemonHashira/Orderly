import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const formatDate = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export const useReportDateRangeQuery = () => {
  const route = useRoute()
  const router = useRouter()

  const from = computed(() => (typeof route.query.from === 'string' ? route.query.from : undefined))
  const to = computed(() => (typeof route.query.to === 'string' ? route.query.to : undefined))

  const updateQuery = async (next: { from?: string; to?: string }) => {
    await router.replace({
      query: {
        ...route.query,
        from: next.from,
        to: next.to,
      },
    })
  }

  const onPreset = async (preset: 'all' | 'last_7' | 'last_30') => {
    if (preset === 'all') {
      await updateQuery({ from: undefined, to: undefined })
      return
    }

    const end = new Date()
    const start = new Date()
    const days = preset === 'last_7' ? 7 : 30
    start.setDate(end.getDate() - (days - 1))

    await updateQuery({
      from: formatDate(start),
      to: formatDate(end),
    })
  }

  return {
    from,
    to,
    updateQuery,
    onPreset,
  }
}
