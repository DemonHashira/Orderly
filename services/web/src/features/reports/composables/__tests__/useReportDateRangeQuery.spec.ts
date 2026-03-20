import { describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { useReportDateRangeQuery } from '@/features/reports/composables/useReportDateRangeQuery'

const TestHarness = defineComponent({
  setup() {
    const state = useReportDateRangeQuery()

    return {
      from: computed(() => state.from.value),
      to: computed(() => state.to.value),
      onPreset: state.onPreset,
      updateQuery: state.updateQuery,
    }
  },
  template: '<div />',
})

describe('useReportDateRangeQuery', () => {
  it('hydrates from route query and updates the query on preset changes', async () => {
    vi.setSystemTime(new Date('2026-03-16T10:00:00'))

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/reports/orders', component: TestHarness }],
    })
    await router.replace('/reports/orders?from=2026-03-01&to=2026-03-14')

    const wrapper = mount(TestHarness, {
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.vm.from).toBe('2026-03-01')
    expect(wrapper.vm.to).toBe('2026-03-14')

    await wrapper.vm.onPreset('last_7')
    await flushPromises()

    expect(router.currentRoute.value.query.from).toBe('2026-03-10')
    expect(router.currentRoute.value.query.to).toBe('2026-03-16')
  })

  it('clears the range when the all-time preset is selected', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/reports/orders', component: TestHarness }],
    })
    await router.replace('/reports/orders?from=2026-03-01&to=2026-03-14')

    const wrapper = mount(TestHarness, {
      global: {
        plugins: [router],
      },
    })

    await wrapper.vm.onPreset('all')
    await flushPromises()
    await nextTick()

    expect(router.currentRoute.value.query).toEqual({})
  })
})
