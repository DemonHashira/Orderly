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
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import { StatusBadge } from '@/shared/ui'
import type { Order } from '@/types'

export type OrdersTableRow = Order & {
  customer_name: string
  sales_channel_name: string
}

type OrdersTableActions = {
  canConfirm: boolean
  canReadyToShip: boolean
  canCancel: boolean
  canEditDraft: boolean
  canDeleteDraft: boolean
  onConfirm: (id: number) => void
  onReadyToShip: (id: number) => void
  onCancel: (id: number) => void
  onDelete: (id: number) => void
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

export const buildOrdersTableColumns = (
  actions: OrdersTableActions,
): Array<ColumnDef<OrdersTableRow>> => {
  return [
    {
      accessorKey: 'reference',
      header: ({ column }) => sortableHeader('Reference', column),
      cell: ({ row }) =>
        h(
          RouterLink,
          {
            to: `/orders/${row.original.id}`,
            class: 'font-medium hover:underline',
          },
          () => row.original.reference,
        ),
    },
    {
      accessorKey: 'current_status',
      header: 'Status',
      cell: ({ row }) => h(StatusBadge, { status: row.original.current_status }),
    },
    {
      accessorKey: 'customer_name',
      header: 'Customer',
      cell: ({ row }) => row.original.customer_name,
    },
    {
      accessorKey: 'sales_channel_name',
      header: 'Sales Channel',
      cell: ({ row }) => row.original.sales_channel_name,
    },
    {
      accessorKey: 'created_at',
      header: ({ column }) => sortableHeader('Created', column, 'center'),
      cell: ({ row }) =>
        h('div', { class: 'text-center tabular-nums' }, formatDateTime(row.original.created_at)),
      meta: {
        align: 'center',
      },
    },
    {
      accessorKey: 'total_amount',
      header: ({ column }) => sortableHeader('Amount', column, 'center'),
      cell: ({ row }) =>
        h(
          'div',
          { class: 'text-center font-medium tabular-nums' },
          formatCurrency(row.original.total_amount),
        ),
      meta: {
        align: 'center',
      },
    },
    {
      id: 'actions',
      header: () => h('div', { class: 'text-right' }, 'Actions'),
      cell: ({ row }) => {
        const order = row.original
        const isDraft = order.current_status === 'draft'
        const isConfirmed = order.current_status === 'confirmed'
        const canCancelState = ['draft', 'confirmed', 'ready_to_ship'].includes(
          order.current_status,
        )

        const menuItems: Array<ReturnType<typeof h> | null> = [
          h(DropdownMenuItem, { asChild: true }, () =>
            h(
              RouterLink,
              {
                to: `/orders/${order.id}`,
              },
              () => 'View details',
            ),
          ),
          actions.canConfirm && isDraft
            ? h(
                DropdownMenuItem,
                {
                  onSelect: () => {
                    actions.onConfirm(order.id)
                  },
                },
                () => 'Confirm',
              )
            : null,
          actions.canReadyToShip && isConfirmed
            ? h(
                DropdownMenuItem,
                {
                  onSelect: () => {
                    actions.onReadyToShip(order.id)
                  },
                },
                () => 'Mark Ready',
              )
            : null,
          actions.canCancel && canCancelState
            ? h(
                DropdownMenuItem,
                {
                  class: 'text-destructive focus:text-destructive',
                  onSelect: () => {
                    actions.onCancel(order.id)
                  },
                },
                () => 'Cancel Order',
              )
            : null,
          actions.canEditDraft && isDraft
            ? h(
                DropdownMenuItem,
                {
                  asChild: true,
                },
                () =>
                  h(
                    RouterLink,
                    {
                      to: `/orders/${order.id}/edit`,
                    },
                    () => 'Edit order',
                  ),
              )
            : null,
          actions.canDeleteDraft && isDraft
            ? h(
                DropdownMenuItem,
                {
                  class: 'text-destructive focus:text-destructive',
                  onSelect: () => {
                    actions.onDelete(order.id)
                  },
                },
                () => 'Delete Draft',
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
                },
                () => h(MoreHorizontal, { class: 'size-4' }),
              ),
            ),
            h(DropdownMenuContent, { align: 'end', class: 'w-44' }, () => [
              h(DropdownMenuLabel, {}, () => 'Row actions'),
              h(DropdownMenuSeparator),
              ...menuItems,
              menuItems.length === 0
                ? h(DropdownMenuItem, { disabled: true }, () => 'No lifecycle actions')
                : null,
            ]),
          ]),
        ])
      },
    },
  ]
}
