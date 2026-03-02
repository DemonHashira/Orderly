export type ApiValidationError = {
  message?: string
  errors?: Record<string, string[]>
}

export type NormalizedApiError = {
  status: number | null
  message: string
  fieldErrors?: Record<string, string[]>
}
