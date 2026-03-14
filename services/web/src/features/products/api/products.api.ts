import { apiClient } from '@/shared/api/client'
import { compactParams } from '@/shared/api/params'
import type {
  PaginatedResponse,
  Product,
  ProductExportFormat,
  ProductImportSummary,
  ProductListParams,
} from '@/types'

export const fetchProducts = async (
  params: ProductListParams = {},
): Promise<PaginatedResponse<Product>> => {
  const { data } = await apiClient.get<PaginatedResponse<Product>>('/api/products', {
    params: compactParams(params),
  })
  return data
}

export const fetchProduct = async (id: number): Promise<{ data: Product }> => {
  const { data } = await apiClient.get<{ data: Product }>(`/api/products/${id}`)
  return data
}

export const createProduct = async (payload: Partial<Product>): Promise<{ data: Product }> => {
  const { data } = await apiClient.post<{ data: Product }>('/api/products', payload)
  return data
}

export const updateProduct = async (
  id: number,
  payload: Partial<Product>,
): Promise<{ data: Product }> => {
  const { data } = await apiClient.patch<{ data: Product }>(`/api/products/${id}`, payload)
  return data
}

export const archiveProduct = async (id: number): Promise<{ data: Product }> => {
  const { data } = await apiClient.post<{ data: Product }>(`/api/products/${id}/archive`)
  return data
}

export const importProducts = async (file: File): Promise<ProductImportSummary> => {
  const formData = new FormData()
  formData.append('file', file)

  const { data } = await apiClient.post<ProductImportSummary>('/api/products/import', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })

  return data
}

const extractFilename = (header?: string | null) => {
  if (!header) {
    return null
  }

  const utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i)
  if (utfMatch?.[1]) {
    return decodeURIComponent(utfMatch[1])
  }

  const plainMatch = header.match(/filename="?([^";]+)"?/i)
  return plainMatch?.[1] ?? null
}

export const exportProducts = async (params: {
  format: ProductExportFormat
  q?: string
  is_active?: boolean
}) => {
  const response = await apiClient.get<Blob>('/api/products/export', {
    params: compactParams(params),
    responseType: 'blob',
  })

  return {
    blob: response.data,
    filename:
      extractFilename(response.headers['content-disposition']) ?? `products.${params.format}`,
  }
}
