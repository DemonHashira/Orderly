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
import { formatDateTime } from '@/lib/formatters'
import { StatusBadge } from '@/shared/ui'
import type { InventoryMovement } from '@/types'

export type InventoryMovementsTableRow = InventoryMovement

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

const formatDelta = (value: number) => (value > 0 ? `+${value}` : String(value))

export const buildInventoryMovementsTableColumns = (): Array<
  ColumnDef<InventoryMovementsTableRow>
> => {
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
      accessorFn: (row) => row.type,
      id: 'type',
      header: () => h('div', { class: 'text-center' }, 'Type'),
      cell: ({ row }) =>
        h('div', { class: 'flex justify-center' }, [h(StatusBadge, { status: row.original.type })]),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.quantity_delta,
      id: 'quantity_delta',
      header: ({ column }) => sortableHeader('Delta', column, 'center'),
      cell: ({ row }) =>
        h(
          'div',
          {
            class: [
              'text-center font-medium tabular-nums',
              row.original.quantity_delta > 0 ? 'text-emerald-600' : '',
              row.original.quantity_delta < 0 ? 'text-destructive' : '',
            ],
          },
          formatDelta(row.original.quantity_delta),
        ),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.reason ?? '',
      id: 'reason',
      header: ({ column }) => sortableHeader('Reason', column),
      cell: ({ row }) => h('span', { class: 'line-clamp-1' }, row.original.reason ?? '-'),
    },
    {
      accessorFn: (row) => row.created_at,
      id: 'created_at',
      header: ({ column }) => sortableHeader('Created', column, 'center'),
      cell: ({ row }) =>
        h('div', { class: 'text-center tabular-nums' }, formatDateTime(row.original.created_at)),
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
                  'aria-label': `Open row actions for movement ${row.original.id}`,
                  'data-test': `inventory-movement-actions-${row.original.id}`,
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
              h(DropdownMenuItem, { asChild: true }, () =>
                h(
                  RouterLink,
                  {
                    to: {
                      path: '/inventory/stocks',
                      query: { q: row.original.product.sku },
                    },
                  },
                  () => 'Inspect stock',
                ),
              ),
            ]),
          ]),
        ]),
    },
  ]
}
