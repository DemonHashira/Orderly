export type ProductDialogMode = 'create' | 'edit'

export type ProductDialogForm = {
  sku: string
  name: string
  sale_price: string
  description: string
  is_active: boolean
}

export type ProductFormField = keyof ProductDialogForm

export const PRODUCT_FORM_FIELDS_TO_VALIDATE: ProductFormField[] = [
  'sku',
  'name',
  'sale_price',
  'description',
  'is_active',
]

export const createEmptyProductDialogForm = (): ProductDialogForm => ({
  sku: '',
  name: '',
  sale_price: '',
  description: '',
  is_active: true,
})
