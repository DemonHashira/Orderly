<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type {
  ReportTableCell,
  ReportTableColumn,
  ReportTableSection,
} from '@/features/reports/model/report-types'

const props = defineProps<{
  section: ReportTableSection
}>()

const cellClass = (cell: ReportTableCell, align: 'left' | 'right' | undefined): string => {
  const alignment = align === 'right' ? 'text-right' : ''

  if (cell.tone === 'mono') {
    return `${alignment} font-mono text-xs`
  }

  if (cell.tone === 'muted') {
    return `${alignment} text-muted-foreground`
  }

  return alignment
}

const resolveCell = (
  row: Record<string, ReportTableCell>,
  column: ReportTableColumn,
): ReportTableCell => row[column.key] ?? { value: '—', tone: 'muted' }
</script>

<template>
  <Card class="dashboard-card-interactive">
    <CardHeader>
      <CardTitle>{{ section.title }}</CardTitle>
      <CardDescription>{{ section.description }}</CardDescription>
    </CardHeader>
    <CardContent class="space-y-4">
      <Alert>
        <AlertTitle>Operator queue</AlertTitle>
        <AlertDescription>
          Keep these lists short and actionable. They should point directly at work that needs
          attention now.
        </AlertDescription>
      </Alert>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead
              v-for="column in section.columns"
              :key="`${section.id}-${column.key}`"
              :class="column.align === 'right' ? 'text-right' : ''"
            >
              {{ column.label }}
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="section.rows.length > 0">
            <TableRow
              v-for="(row, rowIndex) in props.section.rows"
              :key="`${section.id}-${rowIndex}`"
            >
              <TableCell
                v-for="column in section.columns"
                :key="`${section.id}-${rowIndex}-${column.key}`"
                :class="cellClass(resolveCell(row, column), column.align)"
              >
                <Badge
                  v-if="resolveCell(row, column).tone === 'badge'"
                  :variant="resolveCell(row, column).badgeVariant ?? 'secondary'"
                >
                  {{ resolveCell(row, column).value }}
                </Badge>
                <template v-else>{{ resolveCell(row, column).value }}</template>
              </TableCell>
            </TableRow>
          </template>
          <TableEmpty v-else :colspan="section.columns.length">
            {{ section.emptyMessage }}
          </TableEmpty>
        </TableBody>
      </Table>
    </CardContent>
  </Card>
</template>
