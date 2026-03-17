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
import type { Customer } from '@/types'

export type CustomersTableRow = Customer

type CustomersTableActions = {
  canEditCustomers: boolean
  canDeleteCustomers: boolean
  onView: (id: number) => void
  onEdit: (id: number) => void
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

export const buildCustomersTableColumns = (
  actions: CustomersTableActions,
): Array<ColumnDef<CustomersTableRow>> => [
  {
    accessorFn: (row) => row.name,
    id: 'name',
    header: ({ column }) => sortableHeader('Customer', column),
    cell: ({ row }) =>
      h(
        RouterLink,
        {
          to: `/customers/${row.original.id}`,
          class: 'font-medium hover:underline',
          onClick: (event: MouseEvent) => {
            event.preventDefault()
            actions.onView(row.original.id)
          },
        },
        () => row.original.name || `Customer #${row.original.id}`,
      ),
  },
  {
    accessorFn: (row) => row.email ?? '',
    id: 'email',
    header: ({ column }) => sortableHeader('Email', column, 'center'),
    cell: ({ row }) => h('div', { class: 'text-center' }, row.original.email ?? '-'),
    meta: {
      align: 'center',
    },
  },
  {
    accessorFn: (row) => row.phone ?? '',
    id: 'phone',
    header: ({ column }) => sortableHeader('Phone', column, 'center'),
    cell: ({ row }) => h('div', { class: 'text-center tabular-nums' }, row.original.phone ?? '-'),
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
                'aria-label': `Open row actions for customer ${row.original.name || row.original.id}`,
                'data-test': `customer-actions-${row.original.id}`,
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
                  'data-test': `customer-view-${row.original.id}`,
                  onSelect: () => actions.onView(row.original.id),
                },
                () => 'View customer',
              ),
              ...(actions.canEditCustomers
                ? [
                    h(
                      DropdownMenuItem,
                      {
                        'data-test': `customer-edit-${row.original.id}`,
                        onSelect: () => actions.onEdit(row.original.id),
                      },
                      () => 'Edit customer',
                    ),
                  ]
                : []),
              ...(actions.canDeleteCustomers
                ? [
                    h(
                      DropdownMenuItem,
                      {
                        class: 'text-destructive focus:text-destructive',
                        'data-test': `customer-delete-${row.original.id}`,
                        onSelect: () => actions.onDelete(row.original.id),
                      },
                      () => 'Delete customer',
                    ),
                  ]
                : []),
            ]),
          ]),
        ]),
      ]),
  },
]
