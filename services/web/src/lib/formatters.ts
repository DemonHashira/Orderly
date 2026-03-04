export const formatCurrency = (value: number | string): string => {
  const normalized = Number.parseFloat(String(value))

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(Number.isFinite(normalized) ? normalized : 0)
}

export const formatNumber = (value: number | string): string => {
  const normalized = Number.parseFloat(String(value))
  return new Intl.NumberFormat('en-US').format(Number.isFinite(normalized) ? normalized : 0)
}

export const formatDateTime = (value: string | null | undefined): string => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
