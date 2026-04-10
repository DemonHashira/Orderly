import { defineStore } from 'pinia'
import { isPositiveIntegerString } from '@/lib/utils'

export type ListModuleKey =
  | 'orders'
  | 'shipments'
  | 'returns'
  | 'products'
  | 'customers'
  | 'inventory_stocks'
  | 'inventory_movements'
  | 'team_users'

export type ListUiField =
  | 'q'
  | 'status'
  | 'stock_condition'
  | 'type'
  | 'product_id'
  | 'courier'
  | 'sales_channel_id'
  | 'created_from'
  | 'created_to'
  | 'page'
  | 'per_page'

export type ListUiState = {
  q: string
  status: string
  stock_condition: string
  type: string
  product_id: string
  courier: string
  sales_channel_id: string
  created_from: string
  created_to: string
  page: number
  per_page: number
}

type QueryRecord = Record<string, unknown>
type ValidatorMap = Partial<Record<ListUiField, (value: string) => boolean>>

const asSingleQueryValue = (value: unknown): string | undefined => {
  if (typeof value === 'string') {
    return value
  }

  if (Array.isArray(value)) {
    return typeof value[0] === 'string' ? value[0] : undefined
  }

  return undefined
}

const parsePositiveInteger = (value: unknown, fallback: number) => {
  const raw = asSingleQueryValue(value)
  if (!raw) {
    return fallback
  }

  return isPositiveIntegerString(raw) ? Number(raw) : fallback
}

const createDefaultState = (module: ListModuleKey): ListUiState => ({
  q: '',
  status: module === 'orders' ? 'all' : '',
  stock_condition: '',
  type: '',
  product_id: '',
  courier: '',
  sales_channel_id: module === 'orders' ? 'all' : '',
  created_from: '',
  created_to: '',
  page: 1,
  per_page: 15,
})

const createInitialModules = (): Record<ListModuleKey, ListUiState> => ({
  orders: createDefaultState('orders'),
  shipments: createDefaultState('shipments'),
  returns: createDefaultState('returns'),
  products: createDefaultState('products'),
  customers: createDefaultState('customers'),
  inventory_stocks: createDefaultState('inventory_stocks'),
  inventory_movements: createDefaultState('inventory_movements'),
  team_users: createDefaultState('team_users'),
})

const defaultFields: ListUiField[] = [
  'q',
  'status',
  'stock_condition',
  'type',
  'product_id',
  'courier',
  'sales_channel_id',
  'created_from',
  'created_to',
  'page',
  'per_page',
]

const isFieldDifferentFromDefault = (
  state: ListUiState,
  defaults: ListUiState,
  field: ListUiField,
): boolean => {
  if (field === 'page' || field === 'per_page') {
    return state[field] !== defaults[field]
  }

  return state[field].length > 0 && state[field] !== defaults[field]
}

export const useListUiStateStore = defineStore('list-ui-state', {
  state: () => ({
    modules: createInitialModules(),
  }),

  actions: {
    setState(module: ListModuleKey, patch: Partial<ListUiState>) {
      Object.assign(this.modules[module], patch)
    },

    reset(module: ListModuleKey, patch?: Partial<ListUiState>) {
      Object.assign(this.modules[module], createDefaultState(module), patch ?? {})
    },

    resetAll() {
      this.modules = createInitialModules()
    },

    hasRelevantQuery(query: QueryRecord, fields: ListUiField[] = defaultFields) {
      return fields.some((field) => asSingleQueryValue(query[field]) != null)
    },

    hydrateFromQuery(
      module: ListModuleKey,
      query: QueryRecord,
      fields: ListUiField[] = defaultFields,
      validators?: ValidatorMap,
    ) {
      const defaults = createDefaultState(module)
      const next: Partial<ListUiState> = {}

      fields.forEach((field) => {
        if (field === 'page' || field === 'per_page') {
          next[field] = parsePositiveInteger(query[field], defaults[field]) as never
          return
        }

        const raw = asSingleQueryValue(query[field])
        if (!raw) {
          next[field] = defaults[field] as never
          return
        }

        if (validators?.[field] && !validators[field]!(raw)) {
          next[field] = defaults[field] as never
          return
        }

        next[field] = raw as never
      })

      Object.assign(this.modules[module], next)
    },

    toQuery(module: ListModuleKey, fields: ListUiField[] = defaultFields) {
      const state = this.modules[module]
      const defaults = createDefaultState(module)
      const query: Record<string, string> = {}

      fields.forEach((field) => {
        if (!isFieldDifferentFromDefault(state, defaults, field)) {
          return
        }

        if (field === 'page' || field === 'per_page') {
          query[field] = String(state[field])
          return
        }

        query[field] = state[field]
      })

      return query
    },

    normalizeQuery(
      module: ListModuleKey,
      query: QueryRecord,
      fields: ListUiField[] = defaultFields,
      validators?: ValidatorMap,
    ) {
      const defaults = createDefaultState(module)
      const normalized: Partial<ListUiState> = {}

      fields.forEach((field) => {
        if (field === 'page' || field === 'per_page') {
          normalized[field] = parsePositiveInteger(query[field], defaults[field]) as never
          return
        }

        const raw = asSingleQueryValue(query[field])
        if (!raw) {
          normalized[field] = defaults[field] as never
          return
        }

        if (validators?.[field] && !validators[field]!(raw)) {
          normalized[field] = defaults[field] as never
          return
        }

        normalized[field] = raw as never
      })

      const state = {
        ...createDefaultState(module),
        ...normalized,
      }

      const comparable: Record<string, string> = {}
      fields.forEach((field) => {
        if (!isFieldDifferentFromDefault(state, defaults, field)) {
          return
        }

        if (field === 'page' || field === 'per_page') {
          comparable[field] = String(state[field])
          return
        }

        comparable[field] = state[field]
      })

      return comparable
    },
  },
})

export const ORDERS_LIST_FIELDS: ListUiField[] = [
  'q',
  'status',
  'sales_channel_id',
  'created_from',
  'created_to',
  'page',
  'per_page',
]

export const RETURNS_LIST_FIELDS: ListUiField[] = [
  'q',
  'status',
  'created_from',
  'created_to',
  'page',
  'per_page',
]

export const PRODUCTS_LIST_FIELDS: ListUiField[] = ['q', 'status', 'page', 'per_page']

export const INVENTORY_STOCKS_LIST_FIELDS: ListUiField[] = [
  'q',
  'status',
  'stock_condition',
  'page',
  'per_page',
]

export const INVENTORY_MOVEMENTS_LIST_FIELDS: ListUiField[] = [
  'q',
  'type',
  'product_id',
  'created_from',
  'created_to',
  'page',
  'per_page',
]

export const CUSTOMERS_LIST_FIELDS: ListUiField[] = ['q', 'page', 'per_page']

export const BASIC_LIST_FIELDS: ListUiField[] = ['q', 'page']
