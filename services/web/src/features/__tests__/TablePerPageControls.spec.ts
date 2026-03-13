import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import InventoryMovementsDataTable from '@/features/inventory/ui/InventoryMovementsDataTable.vue'
import InventoryStocksDataTable from '@/features/inventory/ui/InventoryStocksDataTable.vue'
import OrdersDataTable from '@/features/orders/ui/OrdersDataTable.vue'
import ProductsDataTable from '@/features/products/ui/ProductsDataTable.vue'
import ReturnsDataTable from '@/features/returns/ui/ReturnsDataTable.vue'
import ShipmentsDataTable from '@/features/shipments/ui/ShipmentsDataTable.vue'

const baseProps = {
  rows: [],
  currentPage: 1,
  totalPages: 1,
  totalRows: 0,
  perPage: 15,
}

describe('table per-page controls', () => {
  it.each([
    [
      'products',
      ProductsDataTable,
      { ...baseProps, canManageProducts: false },
      '[data-test="products-per-page"]',
    ],
    [
      'orders',
      OrdersDataTable,
      {
        ...baseProps,
        canConfirm: false,
        canReadyToShip: false,
        canCancel: false,
        canEditDraft: false,
        canDeleteDraft: false,
        canCreateShipment: false,
      },
      '[data-test="orders-per-page"]',
    ],
    [
      'returns',
      ReturnsDataTable,
      { ...baseProps, canRestock: false },
      '[data-test="returns-per-page"]',
    ],
    [
      'shipments',
      ShipmentsDataTable,
      {
        ...baseProps,
        canMarkDelivered: false,
        canMarkReturned: false,
        canMarkUnpaid: false,
      },
      '[data-test="shipments-per-page"]',
    ],
    [
      'inventory stocks',
      InventoryStocksDataTable,
      baseProps,
      '[data-test="inventory-stocks-per-page"]',
    ],
    [
      'inventory movements',
      InventoryMovementsDataTable,
      baseProps,
      '[data-test="inventory-movements-per-page"]',
    ],
  ])('renders a compact 60px rows-per-page trigger for %s', (_, component, props, selector) => {
    const wrapper = mount(component, {
      props,
    })

    const trigger = wrapper.get(selector)
    const triggerClasses = trigger.classes()

    expect(triggerClasses).toContain('w-[60px]')
    expect(triggerClasses).toContain('max-w-[60px]')
  })
})
