import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ReportSurfaceTabs from '@/features/reports/ui/ReportSurfaceTabs.vue'

describe('ReportSurfaceTabs', () => {
  it('renders pointer-friendly triggers and swaps visible panel content through the shared transition', async () => {
    const wrapper = mount(ReportSurfaceTabs, {
      slots: {
        overview: '<div data-test="overview-panel">Overview content</div>',
        exceptions: '<div data-test="exceptions-panel">Exceptions content</div>',
        breakdowns: '<div data-test="breakdowns-panel">Breakdowns content</div>',
      },
    })

    const triggers = wrapper.findAll('[data-slot="tabs-trigger"]')

    expect(triggers).toHaveLength(3)
    expect(triggers[0]?.classes()).toContain('cursor-pointer')
    expect(wrapper.find('[data-test="overview-panel"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="exceptions-panel"]').exists()).toBe(false)
    expect(wrapper.html()).toContain('dashboard-section')

    await triggers[1]!.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-test="overview-panel"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="exceptions-panel"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="breakdowns-panel"]').exists()).toBe(false)
  })
})
