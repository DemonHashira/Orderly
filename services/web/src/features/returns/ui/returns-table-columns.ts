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
import type { ReturnOrder } from '@/types'

export type ReturnsTableRow = ReturnOrder

type ReturnsTableActions = {
  canRestock: boolean
  onRestock: (id: number) => void
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

const totalReturnedQty = (returnOrder: ReturnOrder): number =>
  (returnOrder.items ?? []).reduce((total, item) => total + item.quantity, 0)

const restockableItemCount = (returnOrder: ReturnOrder): number =>
  (returnOrder.items ?? []).filter((item) => item.restockable).length

const hasRestockableItems = (returnOrder: ReturnOrder): boolean =>
  restockableItemCount(returnOrder) > 0

export const buildReturnsTableColumns = (
  actions: ReturnsTableActions,
): Array<ColumnDef<ReturnsTableRow>> => {
  return [
    {
      accessorFn: (row) => row.order?.reference ?? `#${row.order_id}`,
      id: 'order_reference',
      header: ({ column }) => sortableHeader('Order', column),
      cell: ({ row }) =>
        h(
          RouterLink,
          {
            to: `/returns/${row.original.id}`,
            class: 'font-medium hover:underline',
          },
          () => row.original.order?.reference ?? `#${row.original.order_id}`,
        ),
    },
    {
      accessorFn: (row) => row.reason ?? '',
      id: 'reason',
      header: ({ column }) => sortableHeader('Reason', column),
      cell: ({ row }) => h('span', { class: 'line-clamp-1' }, row.original.reason ?? '-'),
    },
    {
      accessorFn: (row) => row.returned_at ?? '',
      id: 'returned_at',
      header: ({ column }) => sortableHeader('Returned At', column, 'center'),
      cell: ({ row }) =>
        h(
          'div',
          { class: 'text-center tabular-nums' },
          row.original.returned_at ? formatDateTime(row.original.returned_at) : '-',
        ),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => totalReturnedQty(row),
      id: 'qty_total',
      header: ({ column }) => sortableHeader('Returned Qty', column, 'center'),
      cell: ({ row }) =>
        h('div', { class: 'text-center tabular-nums' }, totalReturnedQty(row.original)),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => restockableItemCount(row),
      id: 'restockable_items',
      header: ({ column }) => sortableHeader('Restockable Items', column, 'center'),
      cell: ({ row }) =>
        h('div', { class: 'text-center tabular-nums' }, restockableItemCount(row.original)),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.order?.current_status ?? '',
      id: 'order_status',
      header: () => h('div', { class: 'text-center' }, 'Order Status'),
      cell: ({ row }) =>
        h('div', { class: 'flex justify-center' }, [
          h(StatusBadge, {
            status: row.original.order?.current_status ?? 'returned',
          }),
        ]),
      meta: {
        align: 'center',
      },
    },
    {
      id: 'actions',
      header: () => h('div', { class: 'text-right' }, 'Actions'),
      cell: ({ row }) => {
        const returnOrder = row.original
        const canRestockRow = actions.canRestock && hasRestockableItems(returnOrder)

        const menuItems: Array<ReturnType<typeof h> | null> = [
          h(DropdownMenuItem, { asChild: true }, () =>
            h(
              RouterLink,
              {
                to: `/returns/${returnOrder.id}`,
                'data-test': `returns-view-details-${returnOrder.id}`,
              },
              () => 'View details',
            ),
          ),
          canRestockRow
            ? h(
                DropdownMenuItem,
                {
                  'data-test': `returns-restock-${returnOrder.id}`,
                  onSelect: () => {
                    actions.onRestock(returnOrder.id)
                  },
                },
                () => 'Restock',
              )
            : null,
        ].filter((item): item is ReturnType<typeof h> => item != null)

        return h('div', { class: 'flex justify-end' }, [
          h(DropdownMenu, {}, () => [
            h(DropdownMenuTrigger, { asChild: true }, () =>
              h(
                Button,
                {
                  variant: 'ghost',
                  size: 'icon',
                  class: 'size-8',
                  'aria-label': `Open row actions for return ${returnOrder.id}`,
                  'data-test': `returns-row-actions-${returnOrder.id}`,
                },
                () => h(MoreHorizontal, { class: 'size-4' }),
              ),
            ),
            h(DropdownMenuContent, { align: 'end', class: 'w-44' }, () => [
              h(DropdownMenuLabel, {}, () => 'Row actions'),
              h(DropdownMenuSeparator),
              ...menuItems,
              menuItems.length === 1
                ? h(DropdownMenuItem, { disabled: true }, () => 'No lifecycle actions')
                : null,
            ]),
          ]),
        ])
      },
    },
  ]
}
