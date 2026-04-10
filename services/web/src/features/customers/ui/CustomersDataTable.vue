<script setup lang="ts">
import { computed, ref } from 'vue'
import type { SortingState, Updater } from '@tanstack/vue-table'
import { FlexRender, getCoreRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table'
import { ChevronsLeft, ChevronsRight, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  buildCustomersTableColumns,
  type CustomersTableRow,
} from '@/features/customers/ui/customer-table-columns'

const props = defineProps<{
  rows: CustomersTableRow[]
  currentPage: number
  totalPages: number
  totalRows: number
  perPage: number
  canManageCustomers: boolean
  canEditCustomers?: boolean
  canDeleteCustomers?: boolean
}>()

const emit = defineEmits<{
  (e: 'view', id: number): void
  (e: 'edit', id: number): void
  (e: 'delete', id: number): void
  (e: 'update:page', value: number): void
  (e: 'update:per-page', value: number): void
}>()

const sorting = ref<SortingState>([])

const valueUpdater = <T,>(updaterOrValue: Updater<T>, refValue: { value: T }) => {
  if (typeof updaterOrValue === 'function') {
    refValue.value = (updaterOrValue as (old: T) => T)(refValue.value)
    return
  }

  refValue.value = updaterOrValue
}

const columns = computed(() =>
  buildCustomersTableColumns({
    canEditCustomers: props.canEditCustomers ?? props.canManageCustomers,
    canDeleteCustomers: props.canDeleteCustomers ?? props.canManageCustomers,
    onView: (id) => emit('view', id),
    onEdit: (id) => emit('edit', id),
    onDelete: (id) => emit('delete', id),
  }),
)

const table = useVueTable({
  get data() {
    return props.rows
  },
  get columns() {
    return columns.value
  },
  state: {
    get sorting() {
      return sorting.value
    },
  },
  onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
  getCoreRowModel: getCoreRowModel(),
  getSortedRowModel: getSortedRowModel(),
})

const perPageModel = computed({
  get: () => String(props.perPage),
  set: (value: string) => {
    const next = Number(value)
    if (!Number.isFinite(next) || next <= 0) {
      return
    }

    emit('update:per-page', next)
  },
})

const pageLabel = computed(() => `Page ${props.currentPage} of ${props.totalPages}`)

type ColumnMeta = {
  align?: 'left' | 'center' | 'right'
  className?: string
}

const getColumnClass = (meta?: ColumnMeta) => {
  const classNames: string[] = []

  if (meta?.align === 'right') {
    classNames.push('text-right')
  }

  if (meta?.align === 'center') {
    classNames.push('text-center')
  }

  if (meta?.className) {
    classNames.push(meta.className)
  }

  return classNames
}
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-md border">
      <Table class="table-fixed">
        <TableHeader>
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              :class="getColumnClass(header.column.columnDef.meta as ColumnMeta | undefined)"
            >
              <FlexRender
                v-if="!header.isPlaceholder"
                :render="header.column.columnDef.header"
                :props="header.getContext()"
              />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="table.getRowModel().rows.length === 0">
            <TableCell :colspan="columns.length" class="h-24 text-center text-muted-foreground">
              No customers found.
            </TableCell>
          </TableRow>
          <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
            <TableCell
              v-for="cell in row.getVisibleCells()"
              :key="cell.id"
              :class="getColumnClass(cell.column.columnDef.meta as ColumnMeta | undefined)"
            >
              <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div
      class="flex flex-col gap-2 border-t pt-2 md:grid md:grid-cols-[auto_1fr_auto] md:items-center md:gap-3"
    >
      <div class="flex items-center gap-1 md:justify-self-start">
        <span class="text-muted-foreground text-xs">Rows per page</span>
        <Select v-model="perPageModel">
          <SelectTrigger
            size="sm"
            class="w-[60px] max-w-[60px] px-2 text-sm"
            data-test="customers-per-page"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              <SelectItem value="10">10</SelectItem>
              <SelectItem value="15">15</SelectItem>
              <SelectItem value="25">25</SelectItem>
              <SelectItem value="50">50</SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>

      <p class="text-muted-foreground text-center text-sm">
        {{ pageLabel }} ({{ totalRows }} total)
      </p>

      <div class="flex items-center justify-end gap-1 md:justify-self-end">
        <Button
          variant="outline"
          size="icon"
          :disabled="currentPage <= 1"
          @click="emit('update:page', 1)"
        >
          <ChevronsLeft class="size-4" />
          <span class="sr-only">First page</span>
        </Button>
        <Button
          variant="outline"
          size="icon"
          :disabled="currentPage <= 1"
          @click="emit('update:page', Math.max(1, currentPage - 1))"
        >
          <ChevronLeft class="size-4" />
          <span class="sr-only">Previous page</span>
        </Button>
        <Button
          variant="outline"
          size="icon"
          :disabled="currentPage >= totalPages"
          @click="emit('update:page', Math.min(totalPages, currentPage + 1))"
        >
          <ChevronRight class="size-4" />
          <span class="sr-only">Next page</span>
        </Button>
        <Button
          variant="outline"
          size="icon"
          :disabled="currentPage >= totalPages"
          @click="emit('update:page', totalPages)"
        >
          <ChevronsRight class="size-4" />
          <span class="sr-only">Last page</span>
        </Button>
      </div>
    </div>
  </div>
</template>
