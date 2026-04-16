import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ReportComparisonPanel from '@/features/reports/ui/ReportComparisonPanel.vue'

describe('ReportComparisonPanel', () => {
  it('renders resilient metric cards with signal summary tiles', () => {
    const wrapper = mount(ReportComparisonPanel, {
      props: {
        rangeLabel: '2026-01-30 to 2026-02-28',
        metrics: [
          {
            id: 'total-revenue',
            label: 'Total revenue',
            currentValue: '$21,429.30',
            previousValue: '$3,728.85',
            deltaValue: '+$17,700.45',
            direction: 'up',
            deltaPercentageLabel: '+474.7%',
          },
          {
            id: 'average-order-value',
            label: 'Average order value',
            currentValue: '$128.32',
            previousValue: '$109.67',
            deltaValue: '+$18.65',
            direction: 'up',
            deltaPercentageLabel: '+17.0%',
          },
          {
            id: 'returns',
            label: 'Returns',
            currentValue: '8',
            previousValue: '8',
            deltaValue: 'No change',
            direction: 'flat',
            deltaPercentageLabel: '0.0%',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('+$17,700.45')
    expect(wrapper.text()).toContain('Higher')
    expect(wrapper.text()).toContain('No change')
    expect(wrapper.text()).toContain('Metrics that are higher than the previous period.')
  })

  it('shows the empty all-time state when comparison metrics are unavailable', () => {
    const wrapper = mount(ReportComparisonPanel, {
      props: {
        metrics: [],
        rangeLabel: null,
      },
    })

    expect(wrapper.text()).toContain(
      'All-time reports do not have an adjacent prior range to compare against.',
    )
    expect(wrapper.text()).not.toContain('Comparison metrics above the previous period.')
  })
})
