<script setup lang="ts">
import { computed, ref } from 'vue'
import type { SortingState, Updater } from '@tanstack/vue-table'
import { FlexRender, getCoreRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table'
import { ChevronsLeft, ChevronsRight, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
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
import type { ShipmentsTableRow } from '@/features/shipments/ui/shipments-table-columns'
import { buildShipmentsTableColumns } from '@/features/shipments/ui/shipments-table-columns'

const props = defineProps<{
  rows: ShipmentsTableRow[]
  currentPage: number
  totalPages: number
  totalRows: number
  perPage: number
  canMarkDelivered: boolean
  canMarkReturned: boolean
  canMarkUnpaid: boolean
}>()

const emit = defineEmits<{
  (e: 'mark-delivered', shipmentId: number): void
  (e: 'mark-returned', shipmentId: number): void
  (e: 'mark-unpaid', shipmentId: number): void
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
  buildShipmentsTableColumns({
    canMarkDelivered: props.canMarkDelivered,
    canMarkReturned: props.canMarkReturned,
    canMarkUnpaid: props.canMarkUnpaid,
    onMarkDelivered: (id) => emit('mark-delivered', id),
    onMarkReturned: (id) => emit('mark-returned', id),
    onMarkUnpaid: (id) => emit('mark-unpaid', id),
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

const getAlignmentClass = (align?: string) => {
  if (align === 'right') {
    return 'text-right'
  }
  if (align === 'center') {
    return 'text-center'
  }
  return undefined
}
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              :class="
                getAlignmentClass(
                  (header.column.columnDef.meta as Record<string, string> | undefined)?.align,
                )
              "
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
              No results found.
            </TableCell>
          </TableRow>
          <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
            <TableCell
              v-for="cell in row.getVisibleCells()"
              :key="cell.id"
              :class="
                getAlignmentClass(
                  (cell.column.columnDef.meta as Record<string, string> | undefined)?.align,
                )
              "
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
            class="w-20 min-w-20 px-2 text-sm"
            data-test="shipments-per-page"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="10">10</SelectItem>
            <SelectItem value="15">15</SelectItem>
            <SelectItem value="25">25</SelectItem>
            <SelectItem value="50">50</SelectItem>
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
