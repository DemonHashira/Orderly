import { h } from 'vue'
import { RouterLink } from 'vue-router'
import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown, MoreHorizontal } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import { StatusBadge } from '@/shared/ui'
import type { Product } from '@/types'

export type ProductsTableRow = Product

type ProductsTableActions = {
  canManageProducts: boolean
  onView: (id: number) => void
  onEdit: (id: number) => void
  onArchive: (id: number) => void
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

export const buildProductsTableColumns = (
  actions: ProductsTableActions,
): Array<ColumnDef<ProductsTableRow>> => {
  return [
    {
      accessorFn: (row) => row.name,
      id: 'name',
      header: ({ column }) => sortableHeader('Name', column),
      cell: ({ row }) =>
        h(
          RouterLink,
          {
            to: `/products/${row.original.id}`,
            class: 'font-medium hover:underline',
            onClick: (event: MouseEvent) => {
              event.preventDefault()
              actions.onView(row.original.id)
            },
          },
          () => row.original.name,
        ),
    },
    {
      accessorFn: (row) => row.sku,
      id: 'sku',
      header: ({ column }) => sortableHeader('SKU', column, 'center'),
      cell: ({ row }) => h('div', { class: 'text-center tabular-nums' }, row.original.sku),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.sale_price,
      id: 'sale_price',
      header: ({ column }) => sortableHeader('Sale Price', column, 'center'),
      cell: ({ row }) =>
        h(
          'div',
          { class: 'text-center font-medium tabular-nums' },
          formatCurrency(row.original.sale_price),
        ),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => (row.is_active ? 'active' : 'archived'),
      id: 'status',
      header: () => h('div', { class: 'text-center' }, 'Status'),
      cell: ({ row }) =>
        h('div', { class: 'flex justify-center' }, [
          h(StatusBadge, { status: row.original.is_active ? 'active' : 'archived' }),
        ]),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.updated_at,
      id: 'updated_at',
      header: ({ column }) => sortableHeader('Updated', column, 'center'),
      cell: ({ row }) =>
        h(
          'div',
          { class: 'text-center text-sm tabular-nums' },
          formatDateTime(row.original.updated_at),
        ),
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
                  'aria-label': `Open row actions for product ${row.original.sku}`,
                  'data-test': `product-actions-${row.original.id}`,
                },
                () => h(MoreHorizontal, { class: 'size-4' }),
              ),
            ),
            h(DropdownMenuContent, { align: 'end', class: 'w-44' }, () => [
              h(DropdownMenuLabel, {}, () => 'Row actions'),
              h(DropdownMenuSeparator),
              h(DropdownMenuGroup, {}, () => [
                h(
                  DropdownMenuItem,
                  {
                    'data-test': `product-view-${row.original.id}`,
                    onSelect: () => actions.onView(row.original.id),
                  },
                  () => 'View product',
                ),
                ...(actions.canManageProducts
                  ? [
                      h(
                        DropdownMenuItem,
                        {
                          'data-test': `product-edit-${row.original.id}`,
                          onSelect: () => actions.onEdit(row.original.id),
                        },
                        () => 'Edit product',
                      ),
                    ]
                  : []),
                ...(actions.canManageProducts && row.original.is_active
                  ? [
                      h(DropdownMenuSeparator),
                      h(
                        DropdownMenuItem,
                        {
                          'data-test': `product-archive-${row.original.id}`,
                          onSelect: () => actions.onArchive(row.original.id),
                        },
                        () => 'Archive product',
                      ),
                    ]
                  : []),
              ]),
            ]),
          ]),
        ]),
    },
  ]
}
