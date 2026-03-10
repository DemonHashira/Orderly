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
import type { Shipment } from '@/types'

export type ShipmentsTableRow = Shipment

type ShipmentTableActions = {
  canMarkDelivered: boolean
  canMarkReturned: boolean
  canMarkUnpaid: boolean
  onMarkDelivered: (id: number) => void
  onMarkReturned: (id: number) => void
  onMarkUnpaid: (id: number) => void
}

const statusForShipment = (shipment: Shipment) => shipment.order?.current_status ?? 'shipped'

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

export const buildShipmentsTableColumns = (
  actions: ShipmentTableActions,
): Array<ColumnDef<ShipmentsTableRow>> => {
  return [
    {
      accessorFn: (row) => row.order?.reference ?? `#${row.order_id}`,
      id: 'order_reference',
      header: ({ column }) => sortableHeader('Order', column),
      cell: ({ row }) =>
        h(
          RouterLink,
          {
            to: `/shipments/${row.original.id}`,
            class: 'font-medium hover:underline',
          },
          () => row.original.order?.reference ?? `#${row.original.order_id}`,
        ),
    },
    {
      accessorKey: 'courier',
      header: ({ column }) => sortableHeader('Courier', column, 'center'),
      cell: ({ row }) => h('div', { class: 'text-center' }, row.original.courier),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.tracking_number ?? '',
      id: 'tracking_number',
      header: ({ column }) => sortableHeader('Tracking', column, 'center'),
      cell: ({ row }) =>
        h('div', { class: 'text-center tabular-nums' }, row.original.tracking_number ?? '-'),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.shipped_at ?? '',
      id: 'shipped_at',
      header: ({ column }) => sortableHeader('Shipped', column, 'center'),
      cell: ({ row }) =>
        h(
          'div',
          { class: 'text-center tabular-nums' },
          row.original.shipped_at ? formatDateTime(row.original.shipped_at) : '-',
        ),
      meta: {
        align: 'center',
      },
    },
    {
      accessorFn: (row) => row.delivered_at ?? '',
      id: 'delivered_at',
      header: ({ column }) => sortableHeader('Delivered At', column, 'center'),
      cell: ({ row }) =>
        h(
          'div',
          { class: 'text-center tabular-nums' },
          row.original.delivered_at ? formatDateTime(row.original.delivered_at) : '-',
        ),
      meta: {
        align: 'center',
      },
    },
    {
      id: 'outcome',
      header: () => h('div', { class: 'text-center' }, 'Outcome'),
      cell: ({ row }) =>
        h(
          'div',
          {
            class: 'flex justify-center',
            'data-test': `shipment-status-${row.original.id}`,
          },
          [h(StatusBadge, { status: statusForShipment(row.original) })],
        ),
      meta: {
        align: 'center',
      },
    },
    {
      id: 'actions',
      header: () => h('div', { class: 'text-right' }, 'Actions'),
      cell: ({ row }) => {
        const shipment = row.original
        const shipmentStatus = statusForShipment(shipment)
        const isShipped = shipmentStatus === 'shipped'

        const menuItems: Array<ReturnType<typeof h> | null> = [
          h(DropdownMenuItem, { asChild: true }, () =>
            h(
              RouterLink,
              {
                to: `/shipments/${shipment.id}`,
              },
              () => 'View details',
            ),
          ),
          actions.canMarkDelivered && isShipped
            ? h(
                DropdownMenuItem,
                {
                  'data-test': `shipments-mark-delivered-${shipment.id}`,
                  onSelect: () => {
                    actions.onMarkDelivered(shipment.id)
                  },
                },
                () => 'Mark Delivered',
              )
            : null,
          actions.canMarkReturned && isShipped
            ? h(
                DropdownMenuItem,
                {
                  'data-test': `shipments-mark-returned-${shipment.id}`,
                  onSelect: () => {
                    actions.onMarkReturned(shipment.id)
                  },
                },
                () => 'Mark Returned',
              )
            : null,
          actions.canMarkUnpaid && isShipped
            ? h(
                DropdownMenuItem,
                {
                  'data-test': `shipments-mark-unpaid-${shipment.id}`,
                  class: 'text-destructive focus:text-destructive',
                  onSelect: () => {
                    actions.onMarkUnpaid(shipment.id)
                  },
                },
                () => 'Mark Unpaid',
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
                  'aria-label': `Open row actions for shipment ${shipment.id}`,
                  'data-test': `shipments-row-actions-${shipment.id}`,
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
