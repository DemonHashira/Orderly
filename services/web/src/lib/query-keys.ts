export const authKeys = {
  all: ['auth'] as const,
  me: () => [...authKeys.all, 'me'] as const,
}

export const dashboardKeys = {
  all: ['dashboard'] as const,
  summary: (params: Record<string, unknown>) => [...dashboardKeys.all, 'summary', params] as const,
}

export const ordersKeys = {
  all: ['orders'] as const,
  list: (params: Record<string, unknown>) => [...ordersKeys.all, 'list', params] as const,
  detail: (id: number) => [...ordersKeys.all, 'detail', id] as const,
}

export const shipmentsKeys = {
  all: ['shipments'] as const,
  list: (params: Record<string, unknown>) => [...shipmentsKeys.all, 'list', params] as const,
  detail: (id: number) => [...shipmentsKeys.all, 'detail', id] as const,
}

export const returnsKeys = {
  all: ['returns'] as const,
  list: (params: Record<string, unknown>) => [...returnsKeys.all, 'list', params] as const,
  detail: (id: number) => [...returnsKeys.all, 'detail', id] as const,
  byOrder: (orderId: number) => [...returnsKeys.all, 'by-order', orderId] as const,
}

export const inventoryKeys = {
  all: ['inventory'] as const,
  stocks: (params: Record<string, unknown>) => [...inventoryKeys.all, 'stocks', params] as const,
  movements: (params: Record<string, unknown>) =>
    [...inventoryKeys.all, 'movements', params] as const,
}

export const customersKeys = {
  all: ['customers'] as const,
  list: (params: Record<string, unknown>) => [...customersKeys.all, 'list', params] as const,
  detail: (id: number) => [...customersKeys.all, 'detail', id] as const,
}

export const productsKeys = {
  all: ['products'] as const,
  list: (params: Record<string, unknown>) => [...productsKeys.all, 'list', params] as const,
  detail: (id: number) => [...productsKeys.all, 'detail', id] as const,
}

export const salesChannelsKeys = {
  all: ['sales-channels'] as const,
  list: (params: Record<string, unknown>) => [...salesChannelsKeys.all, 'list', params] as const,
}

export const lookupKeys = {
  all: ['lookups'] as const,
  orderCreate: () => [...lookupKeys.all, 'order-create'] as const,
}

export const adminUsersKeys = {
  all: ['admin-users'] as const,
  list: (params: Record<string, unknown>) => [...adminUsersKeys.all, 'list', params] as const,
  roles: () => [...adminUsersKeys.all, 'roles'] as const,
}
