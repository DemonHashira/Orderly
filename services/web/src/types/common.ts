export type PaginationParams = {
  page?: number
  per_page?: number
}

export type SearchParams = {
  q?: string
}

export type DateRangeParams = {
  from?: string
  to?: string
}

export type PaginatedLinks = {
  first?: string | null
  last?: string | null
  prev?: string | null
  next?: string | null
}

export type PaginatedMeta = {
  current_page: number
  from: number | null
  last_page: number
  links: Array<{ url: string | null; label: string; active: boolean }>
  path: string
  per_page: number
  to: number | null
  total: number
}

export type PaginatedResponse<T> = {
  data: T[]
  links: PaginatedLinks
  meta: PaginatedMeta
}

export type ResourceResponse<T> = {
  data: T
}
