import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
import ReportActionLinksPanel from '@/features/reports/ui/ReportActionLinksPanel.vue'

const makeRouter = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/orders', component: { template: '<div />' } },
      { path: '/returns', component: { template: '<div />' } },
    ],
  })

  await router.replace('/')
  return router
}

describe('ReportActionLinksPanel', () => {
  it('collapses to a compact single-action row when only one workspace shortcut is available', async () => {
    const router = await makeRouter()
    const wrapper = mount(ReportActionLinksPanel, {
      props: {
        actions: [
          {
            id: 'open-orders-backlog',
            label: 'Open backlog orders',
            description: 'Review draft, confirmed, and ready-to-ship orders.',
            to: {
              path: '/orders',
              query: {
                status: 'ready_to_ship',
              },
            },
          },
        ],
      },
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('Open backlog orders')
    expect(wrapper.text()).toContain('Review draft, confirmed, and ready-to-ship orders.')
    expect(wrapper.text()).not.toContain('Recommended actions')
    expect(wrapper.text()).not.toContain(
      'Use these drilldowns to move from summary metrics into the operational workspace.',
    )
  })

  it('keeps the section framing when multiple shortcuts are available', async () => {
    const router = await makeRouter()
    const wrapper = mount(ReportActionLinksPanel, {
      props: {
        actions: [
          {
            id: 'open-orders-backlog',
            label: 'Open backlog orders',
            description: 'Review draft, confirmed, and ready-to-ship orders.',
            to: {
              path: '/orders',
              query: {
                status: 'ready_to_ship',
              },
            },
          },
          {
            id: 'open-returns-workspace',
            label: 'Open restock queue',
            description: 'Review returns that still have restockable quantity.',
            to: {
              path: '/returns',
              query: {
                status: 'restockable',
              },
            },
          },
        ],
      },
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('Recommended actions')
    expect(wrapper.text()).toContain(
      'Use these drilldowns to move from summary metrics into the operational workspace.',
    )
  })
})
