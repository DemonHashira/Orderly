import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { reportsKeys } from '@/lib/query-keys'
import { fetchOrdersReportSummary } from '@/features/reports/api/reports.api'
import type { DateRangeParams } from '@/types'

export const useOrdersReportSummaryQuery = (params: MaybeRefOrGetter<DateRangeParams>) => {
  const queryParams = computed(() => toValue(params))

  return useQuery({
    queryKey: computed(() => reportsKeys.ordersSummary(queryParams.value)),
    queryFn: () => fetchOrdersReportSummary(queryParams.value),
    placeholderData: keepPreviousData,
    retry: false,
  })
}
