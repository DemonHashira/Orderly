import type { DashboardChartBlockId } from './model'

export type DashboardKpiCard = {
  id: string
  title: string
  value: string
  description: string
}

export type DashboardChartCard = {
  id: DashboardChartBlockId
  title: string
  description: string
  points: Array<{ label: string; value: number }>
}

export type KpiCard = DashboardKpiCard
export type ChartCard = DashboardChartCard
