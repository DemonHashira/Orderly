import { h } from 'vue'
import { RouterLink } from 'vue-router'
import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown, MoreHorizontal } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { StatusBadge } from '@/shared/ui'
import type { InventoryStock } from '@/types'

export type InventoryStocksTableRow = InventoryStock

type InventoryStocksTableActions = {
  onOpenMovements: (productId: number) => void
}

const sortableHeader = (
  label: string,
  column: { toggleSorting: (desc?: boolean) => void; getIsSorted: () => false | 'asc' | 'desc' },
  align: 'left' | 'center' | 'right' = 'left',
) =>
  h(
    Button,
    {
      variant: 'ghost',
      class:
        align === 'right'
          ? 'h-8 justify-end px-0 hover:bg-transparent'
          : align === 'center'
            ? 'h-8 justify-center px-0 hover:bg-transparent'
            : 'h-8 px-0 hover:bg-transparent',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
    },
    () => [label, h(ArrowUpDown, { class: 'ml-1 size-3.5' })],
  )

export const buildInventoryStocksTableColumns = (
  actions: InventoryStocksTableActions,
): Array<ColumnDef<InventoryStocksTableRow>> => {
  return [
    {
      accessorFn: (row) => row.product.name,
      id: 'product_name',
      header: ({ column }) => sortableHeader('Product', column),
      cell: ({ row }) =>
        h(
          RouterLink,
          {
            to: `/products/${row.original.product.id}`,
            class: 'font-medium hover:underline',
          },
          () => row.original.product.name,
        ),
    },
    {
      accessorFn: (row) => row.product.sku,
      id: 'sku',
      header: ({ column }) => sortableHeader('SKU', column, 'center'),
      cell: ({ row }) => h('div', { class: 'text-center tabular-nums' }, row.original.product.sku),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => (row.product.is_active ? 'active' : 'archived'),
      id: 'status',
      header: () => h('div', { class: 'text-center' }, 'Status'),
      cell: ({ row }) =>
        h('div', { class: 'flex justify-center' }, [
          h(StatusBadge, { status: row.original.product.is_active ? 'active' : 'archived' }),
        ]),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.qty_on_hand,
      id: 'qty_on_hand',
      header: ({ column }) => sortableHeader('On Hand', column, 'center'),
      cell: ({ row }) => h('div', { class: 'text-center tabular-nums' }, row.original.qty_on_hand),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.qty_reserved,
      id: 'qty_reserved',
      header: ({ column }) => sortableHeader('Reserved', column, 'center'),
      cell: ({ row }) => h('div', { class: 'text-center tabular-nums' }, row.original.qty_reserved),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.available,
      id: 'available',
      header: ({ column }) => sortableHeader('Available', column, 'center'),
      cell: ({ row }) =>
        h('div', { class: 'text-center font-medium tabular-nums' }, row.original.available),
      meta: {
        align: 'center',
      },
    },
    {
      id: 'actions',
      header: () => h('div', { class: 'text-right' }, 'Actions'),
      cell: ({ row }) =>
        h('div', { class: 'flex justify-end' }, [
          h(DropdownMenu, {}, () => [
            h(DropdownMenuTrigger, { asChild: true }, () =>
              h(
                Button,
                {
                  variant: 'ghost',
                  size: 'icon',
                  class: 'size-8',
                  'aria-label': `Open row actions for stock ${row.original.product.sku}`,
                  'data-test': `inventory-stock-actions-${row.original.product.id}`,
                },
                () => h(MoreHorizontal, { class: 'size-4' }),
              ),
            ),
            h(DropdownMenuContent, { align: 'end', class: 'w-44' }, () => [
              h(DropdownMenuLabel, {}, () => 'Row actions'),
              h(DropdownMenuSeparator),
              h(DropdownMenuItem, { asChild: true }, () =>
                h(
                  RouterLink,
                  {
                    to: `/products/${row.original.product.id}`,
                  },
                  () => 'View product',
                ),
              ),
              h(
                DropdownMenuItem,
                {
                  'data-test': `inventory-stock-open-movements-${row.original.product.id}`,
                  onSelect: () => {
                    actions.onOpenMovements(row.original.product.id)
                  },
                },
                () => 'Open movement history',
              ),
            ]),
          ]),
        ]),
    },
  ]
}
