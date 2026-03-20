import { computed } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { toValue } from 'vue'
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { dashboardKeys } from '@/lib/query-keys'
import { fetchDashboardSummary } from '@/features/dashboard/api/dashboard.api'
import type { DateRangeParams } from '@/types'

type DashboardSummaryQueryOptions = {
  enabled?: MaybeRefOrGetter<boolean>
}

export const useDashboardSummaryQuery = (
  params: MaybeRefOrGetter<DateRangeParams>,
  options: DashboardSummaryQueryOptions = {},
) => {
  const queryParams = computed(() => toValue(params))
  const enabled = computed(() => toValue(options.enabled) ?? true)

  return useQuery({
    queryKey: computed(() => dashboardKeys.summary(queryParams.value)),
    queryFn: () => fetchDashboardSummary(queryParams.value),
    placeholderData: keepPreviousData,
    retry: false,
    enabled,
  })
}
