import type { CountMap } from '@/types'

export type PieChartPoint = {
  label: string
  value: number
}

export const mapCountMapToChart = (input?: CountMap): PieChartPoint[] => {
  if (!input) {
    return []
  }

  return Object.entries(input)
    .map(([key, value]) => ({
      label: key.replace(/_/g, ' '),
      value,
    }))
    .sort((a, b) => b.value - a.value)
}

export const mapInventoryFlowToChart = (movementIn = 0, movementOut = 0): PieChartPoint[] => [
  { label: 'In', value: movementIn },
  { label: 'Out', value: movementOut },
]
