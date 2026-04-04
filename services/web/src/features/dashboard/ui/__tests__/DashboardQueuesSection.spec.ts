import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DashboardQueuesSection from '@/features/dashboard/ui/DashboardQueuesSection.vue'

describe('DashboardQueuesSection', () => {
  it('renders per-card view all links with expected routes', () => {
    const wrapper = mount(DashboardQueuesSection, {
      props: {
        queueOrder: [
          'ready-to-ship',
          'shipment-follow-up',
          'returns-to-restock',
          'inventory-attention',
        ],
        readyOrders: [],
        returnsToRestock: [],
        followUpShipments: [],
        lowAvailabilityStocks: [],
        queueLoading: {
          readyToShip: false,
          returnsToRestock: false,
          shipmentFollowUp: false,
          inventoryAttention: false,
        },
        queueErrors: {
          readyToShip: false,
          returnsToRestock: false,
          shipmentFollowUp: false,
          inventoryAttention: false,
        },
      },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="to"><slot /></a>',
          },
        },
      },
    })

    const links = wrapper
      .findAll('a')
      .filter((node) => node.text().trim() === 'View all')
      .map((node) => node.attributes('href'))

    expect(links).toEqual([
      '/orders?status=ready_to_ship',
      '/shipments',
      '/returns?has_restockable=true',
      '/inventory/stocks?stock_condition=low_stock&status=active',
    ])
  })

  it('renders view-all links only for queue cards included in queueOrder', () => {
    const wrapper = mount(DashboardQueuesSection, {
      props: {
        queueOrder: ['ready-to-ship', 'inventory-attention'],
        readyOrders: [],
        returnsToRestock: [],
        followUpShipments: [],
        lowAvailabilityStocks: [],
        queueLoading: {
          readyToShip: false,
          returnsToRestock: false,
          shipmentFollowUp: false,
          inventoryAttention: false,
        },
        queueErrors: {
          readyToShip: false,
          returnsToRestock: false,
          shipmentFollowUp: false,
          inventoryAttention: false,
        },
      },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="to"><slot /></a>',
          },
        },
      },
    })

    const links = wrapper
      .findAll('a')
      .filter((node) => node.text().trim() === 'View all')
      .map((node) => node.attributes('href'))

    expect(links).toEqual([
      '/orders?status=ready_to_ship',
      '/inventory/stocks?stock_condition=low_stock&status=active',
    ])
  })
})
