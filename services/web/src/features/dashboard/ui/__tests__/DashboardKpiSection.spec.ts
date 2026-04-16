import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DashboardKpiSection from '@/features/dashboard/ui/DashboardKpiSection.vue'

describe('DashboardKpiSection', () => {
  it('keeps KPI values on one line and exposes the full value as a title fallback', () => {
    const wrapper = mount(DashboardKpiSection, {
      props: {
        isLoading: false,
        hasError: false,
        cards: [
          {
            id: 'orders-revenue',
            title: 'Total Revenue',
            value: '$25,951.50',
            description: 'Total booked revenue across matching orders',
          },
        ],
      },
    })

    const value = wrapper.get('[data-test="dashboard-kpi-value-orders-revenue"]')

    expect(value.attributes('title')).toBe('$25,951.50')
    expect(value.classes()).toContain('whitespace-nowrap')
    expect(value.classes()).toContain('overflow-hidden')
    expect(value.classes()).toContain('text-ellipsis')
  })
})
