import { describe, expect, it } from 'vitest'
import { mapCountMapToChart, mapInventoryFlowToChart } from '@/features/dashboard/ui/chart-adapters'

describe('dashboard chart adapters', () => {
  it('maps count maps to sorted chart points', () => {
    const data = mapCountMapToChart({ confirmed: 5, draft: 2 })

    expect(data).toEqual([
      { label: 'confirmed', value: 5 },
      { label: 'draft', value: 2 },
    ])
  })

  it('returns empty array when count map is missing', () => {
    expect(mapCountMapToChart(undefined)).toEqual([])
  })

  it('maps inventory in/out values', () => {
    expect(mapInventoryFlowToChart(12, 4)).toEqual([
      { label: 'In', value: 12 },
      { label: 'Out', value: 4 },
    ])
  })
})
