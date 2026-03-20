import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { reportsKeys } from '@/lib/query-keys'
import { fetchReturnsReportSummary } from '@/features/reports/api/reports.api'
import type { DateRangeParams } from '@/types'

export const useReturnsReportSummaryQuery = (params: MaybeRefOrGetter<DateRangeParams>) => {
  const queryParams = computed(() => toValue(params))

  return useQuery({
    queryKey: computed(() => reportsKeys.returnsSummary(queryParams.value)),
    queryFn: () => fetchReturnsReportSummary(queryParams.value),
    placeholderData: keepPreviousData,
    retry: false,
  })
}
