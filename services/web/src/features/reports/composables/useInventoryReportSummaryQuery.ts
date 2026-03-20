import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { reportsKeys } from '@/lib/query-keys'
import { fetchInventoryReportSummary } from '@/features/reports/api/reports.api'
import type { DateRangeParams } from '@/types'

export const useInventoryReportSummaryQuery = (params: MaybeRefOrGetter<DateRangeParams>) => {
  const queryParams = computed(() => toValue(params))

  return useQuery({
    queryKey: computed(() => reportsKeys.inventorySummary(queryParams.value)),
    queryFn: () => fetchInventoryReportSummary(queryParams.value),
    placeholderData: keepPreviousData,
    retry: false,
  })
}
