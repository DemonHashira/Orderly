<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Download, Plus, Search, Upload } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useInventoryStocksQuery } from '@/features/inventory/composables/useInventoryQueries'
import {
  useArchiveProductMutation,
  useCreateProductMutation,
  useExportProductsMutation,
  useImportProductsMutation,
  useProductQuery,
  useProductsQuery,
  useUpdateProductMutation,
} from '@/features/products/composables/useProductsQueries'
import { buildSuggestedProductSku } from '@/features/products/sku'
import {
  PRODUCT_FORM_FIELDS_TO_VALIDATE,
  createEmptyProductDialogForm,
  type ProductDialogForm,
  type ProductFormField,
} from '@/features/products/types'
import ProductForm from '@/features/products/ui/ProductForm.vue'
import {
  validateProductDialogField,
  validateProductDialogForm,
} from '@/features/products/validation/product.schema'
import ProductsDataTable from '@/features/products/ui/ProductsDataTable.vue'
import { formatCurrency, formatDateTime } from '@/lib/formatters'
import type { Product, ProductExportFormat, ProductImportSummary } from '@/types'
import { normalizeApiError } from '@/shared/api/errors'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  StatusBadge,
} from '@/shared/ui'
import { PRODUCTS_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'

const PRODUCT_STATUS_OPTIONS = ['all', 'active', 'archived'] as const

const { permissions } = useAuth()
const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const listModule = 'products' as const
const isSyncingFromRoute = ref(false)

const productMutationError = ref('')
const productFieldErrors = ref<Record<string, string>>({})
const productServerFieldErrors = ref<Record<string, string>>({})
const productClientFieldErrors = ref<Record<string, string>>({})
const pendingArchiveProductId = ref<number | null>(null)
const importDialogOpen = ref(false)
const importFile = ref<File | null>(null)
const importFieldErrors = ref<Record<string, string>>({})
const importSubmitError = ref('')
const importSummary = ref<ProductImportSummary | null>(null)
const exportSubmitError = ref('')
const persistedDetailProduct = ref<Product | null>(null)
const productForm = ref(createEmptyProductDialogForm())
const touchedProductFields = reactive<Record<ProductFormField, boolean>>({
  sku: false,
  name: false,
  sale_price: false,
  description: false,
  is_active: false,
})

const page = computed({
  get: () => listUiStore.modules[listModule].page,
  set: (value: number) => listUiStore.setState(listModule, { page: value }),
})
const perPage = computed({
  get: () => listUiStore.modules[listModule].per_page,
  set: (value: number) => listUiStore.setState(listModule, { per_page: value }),
})
const searchInput = computed({
  get: () => listUiStore.modules[listModule].q,
  set: (value: string) => listUiStore.setState(listModule, { q: value }),
})
const statusFilter = computed<(typeof PRODUCT_STATUS_OPTIONS)[number]>({
  get: () => {
    const value = listUiStore.modules[listModule].status
    if (value === 'active' || value === 'archived') {
      return value
    }

    return 'all'
  },
  set: (value) => listUiStore.setState(listModule, { status: value === 'all' ? '' : value }),
})

const debouncedSearch = useDebouncedRef(searchInput)
const isCreateRoute = computed(() => route.name === 'product-create')
const isDetailRoute = computed(() => route.name === 'product-detail')
const isEditRoute = computed(() => route.name === 'product-edit')

const canViewProducts = computed(() => permissions.value.includes('products.view'))
const canManageProducts = computed(() => permissions.value.includes('products.manage'))
const canImportProducts = computed(() => permissions.value.includes('products.import'))
const canExportProducts = computed(() => permissions.value.includes('products.export'))
const canViewInventory = computed(() => permissions.value.includes('inventory.view'))

const productsQuery = useProductsQuery(
  computed(() => ({
    page: page.value,
    per_page: perPage.value,
    q: debouncedSearch.value || undefined,
    is_active: statusFilter.value === 'all' ? undefined : statusFilter.value === 'active',
  })),
  {
    enabled: computed(() => canViewProducts.value && !isCreateRoute.value && !isEditRoute.value),
  },
)

const createProductMutation = useCreateProductMutation()
const updateProductMutation = useUpdateProductMutation()
const archiveProductMutation = useArchiveProductMutation()
const importProductsMutation = useImportProductsMutation()
const exportProductsMutation = useExportProductsMutation()

const products = computed(() => productsQuery.data.value?.data ?? [])
const meta = computed(() => productsQuery.data.value?.meta)
const isInitialLoading = useInitialLoadingGate(productsQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && productsQuery.isFetching.value)

const detailProductId = computed(() => {
  if (!isDetailRoute.value && !isEditRoute.value) {
    return 0
  }

  return Number(route.params.id)
})

const detailProductQuery = useProductQuery(detailProductId)
const detailProduct = computed(() => detailProductQuery.data.value?.data ?? null)
const detailProductForDialog = computed(() => detailProduct.value ?? persistedDetailProduct.value)
const isDetailDialogLoading = computed(
  () => (isDetailRoute.value || isEditRoute.value) && detailProductQuery.isLoading.value,
)

const detailStockQuery = useInventoryStocksQuery(
  computed(() => ({
    page: 1,
    per_page: 25,
    q: detailProductForDialog.value?.sku ?? undefined,
  })),
  {
    enabled: computed(
      () =>
        canViewInventory.value &&
        detailProductForDialog.value != null &&
        (isDetailRoute.value || isEditRoute.value),
    ),
    keepPreviousData: true,
  },
)

const detailStockSummary = computed(() => {
  if (!detailProductForDialog.value) {
    return null
  }

  return (
    detailStockQuery.data.value?.data.find(
      (stock) => stock.product.id === detailProductForDialog.value?.id,
    ) ?? null
  )
})

const resetProductFieldValidation = () => {
  productFieldErrors.value = {}
  productServerFieldErrors.value = {}
  productClientFieldErrors.value = {}

  PRODUCT_FORM_FIELDS_TO_VALIDATE.forEach((field) => {
    touchedProductFields[field] = false
  })
}

const setProductClientFieldError = (field: ProductFormField, message: string | null) => {
  if (message) {
    productClientFieldErrors.value[field] = message
  } else {
    delete productClientFieldErrors.value[field]
  }

  productFieldErrors.value = {
    ...productServerFieldErrors.value,
    ...productClientFieldErrors.value,
  }
}

const clearProductServerFieldError = (field: ProductFormField) => {
  if (!productServerFieldErrors.value[field]) {
    return
  }

  delete productServerFieldErrors.value[field]
  productServerFieldErrors.value = { ...productServerFieldErrors.value }
  productFieldErrors.value = {
    ...productServerFieldErrors.value,
    ...productClientFieldErrors.value,
  }
}

const validateProductField = (field: ProductFormField) => {
  const message = validateProductDialogField(productForm.value, field)
  setProductClientFieldError(field, message)
}

const onProductFieldBlur = (field: ProductFormField) => {
  touchedProductFields[field] = true
  validateProductField(field)
}

const onProductFieldInput = (field: ProductFormField) => {
  clearProductServerFieldError(field)

  if (!touchedProductFields[field]) {
    return
  }

  validateProductField(field)
}

const buildProductPayload = (form: ProductDialogForm) => ({
  sku: form.sku.trim().toUpperCase(),
  name: form.name.trim(),
  sale_price: String(form.sale_price ?? '').trim(),
  description: form.description.trim() || null,
  is_active: form.is_active,
})

const onGenerateProductSku = () => {
  const suggestedSku = buildSuggestedProductSku(productForm.value.name)
  if (!suggestedSku) {
    return
  }

  productForm.value = {
    ...productForm.value,
    sku: suggestedSku,
  }
  touchedProductFields.sku = true
  clearProductServerFieldError('sku')
  validateProductField('sku')
}

watch(
  detailProduct,
  (product) => {
    if (product) {
      persistedDetailProduct.value = product
    }
  },
  { immediate: true },
)

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>

    if (!listUiStore.hasRelevantQuery(normalizedQuery, PRODUCTS_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, PRODUCTS_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, PRODUCTS_LIST_FIELDS, {
      status: (value: string) =>
        PRODUCT_STATUS_OPTIONS.includes(value as (typeof PRODUCT_STATUS_OPTIONS)[number]),
    })

    void nextTick().then(() => {
      isSyncingFromRoute.value = false
    })
  },
  { immediate: true },
)

watch([debouncedSearch, statusFilter], () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch(perPage, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch([debouncedSearch, statusFilter, page, perPage], () => {
  if (isSyncingFromRoute.value) {
    return
  }

  const nextQuery = {
    ...listUiStore.toQuery(listModule, PRODUCTS_LIST_FIELDS),
    ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
  }
  const normalizedCurrentQuery = listUiStore.normalizeQuery(
    listModule,
    route.query as Record<string, unknown>,
    PRODUCTS_LIST_FIELDS,
    {
      status: (value: string) =>
        PRODUCT_STATUS_OPTIONS.includes(value as (typeof PRODUCT_STATUS_OPTIONS)[number]),
    },
  )

  if (JSON.stringify(nextQuery) === JSON.stringify(normalizedCurrentQuery)) {
    return
  }

  void router.replace({ query: nextQuery })
})

watch([isCreateRoute, isEditRoute, detailProductForDialog], () => {
  resetProductFieldValidation()
  productMutationError.value = ''

  if (isCreateRoute.value) {
    productForm.value = createEmptyProductDialogForm()
    return
  }

  if (isEditRoute.value && detailProductForDialog.value) {
    productForm.value = {
      sku: detailProductForDialog.value.sku,
      name: detailProductForDialog.value.name,
      sale_price: detailProductForDialog.value.sale_price,
      description: detailProductForDialog.value.description ?? '',
      is_active: detailProductForDialog.value.is_active,
    }
  }
})

watch(importDialogOpen, (open) => {
  if (!open) {
    importFile.value = null
    importFieldErrors.value = {}
    importSubmitError.value = ''
    importSummary.value = null
  }
})

const mapFieldErrors = (errors?: Record<string, string[]>) => {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages?.[0] ?? 'Invalid value']),
  )
}

const resetFilters = () => {
  searchInput.value = ''
  statusFilter.value = 'all'
  perPage.value = 15
  page.value = 1
}

const openCreateDialog = async () => {
  await router.push({
    name: 'product-create',
    query: route.query,
  })
}

const openProductDetail = async (productId: number) => {
  await router.push({
    name: 'product-detail',
    params: { id: String(productId) },
    query: route.query,
  })
}

const openProductEdit = async (productId: number) => {
  await router.push({
    name: 'product-edit',
    params: { id: String(productId) },
    query: route.query,
  })
}

const closeProductDetailDialog = async () => {
  await router.push({
    name: 'products',
    query: route.query,
  })
}

const closeProductFormDialog = async () => {
  if (isEditRoute.value && detailProductId.value > 0) {
    await router.push({
      name: 'product-detail',
      params: { id: String(detailProductId.value) },
      query: route.query,
    })
    return
  }

  await router.push({
    name: 'products',
    query: route.query,
  })
}

const isProductDetailDialogOpen = computed({
  get: () => isDetailRoute.value,
  set: (value: boolean) => {
    if (!value) {
      void closeProductDetailDialog()
    }
  },
})

const isProductFormDialogOpen = computed({
  get: () => isCreateRoute.value || isEditRoute.value,
  set: (value: boolean) => {
    if (!value) {
      void closeProductFormDialog()
    }
  },
})

const archiveDialogOpen = computed({
  get: () => pendingArchiveProductId.value != null,
  set: (value: boolean) => {
    if (!value) {
      pendingArchiveProductId.value = null
    }
  },
})

const openArchiveDialog = (productId: number) => {
  productMutationError.value = ''
  pendingArchiveProductId.value = productId
}

const onArchiveProduct = async () => {
  if (pendingArchiveProductId.value == null) {
    return
  }

  productMutationError.value = ''

  try {
    await archiveProductMutation.mutateAsync(pendingArchiveProductId.value)
    toast.success('Product archived successfully.')
    pendingArchiveProductId.value = null
  } catch (error: unknown) {
    productMutationError.value = normalizeApiError(error).message
  }
}

const isProductFormPending = computed(
  () => createProductMutation.isPending.value || updateProductMutation.isPending.value,
)

const submitProductForm = async () => {
  productMutationError.value = ''

  const clientErrors = validateProductDialogForm(productForm.value)
  if (Object.keys(clientErrors).length > 0) {
    productClientFieldErrors.value = clientErrors
    productFieldErrors.value = {
      ...productServerFieldErrors.value,
      ...productClientFieldErrors.value,
    }
    PRODUCT_FORM_FIELDS_TO_VALIDATE.forEach((field) => {
      touchedProductFields[field] = true
    })
    return
  }

  productClientFieldErrors.value = {}
  productServerFieldErrors.value = {}
  productFieldErrors.value = {}
  const payload = buildProductPayload(productForm.value)

  try {
    if (isEditRoute.value && detailProductId.value > 0) {
      const response = await updateProductMutation.mutateAsync({
        id: detailProductId.value,
        payload,
      })

      toast.success('Product updated successfully.')
      await router.push({
        name: 'product-detail',
        params: { id: String(response.data.id) },
        query: route.query,
      })
      return
    }

    const response = await createProductMutation.mutateAsync(payload)
    toast.success('Product created successfully.')
    await router.push({
      name: 'product-detail',
      params: { id: String(response.data.id) },
      query: route.query,
    })
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    productServerFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    productFieldErrors.value = {
      ...productServerFieldErrors.value,
      ...productClientFieldErrors.value,
    }
    productMutationError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const onImportFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  importFile.value = target.files?.[0] ?? null
}

const submitImport = async () => {
  importFieldErrors.value = {}
  importSubmitError.value = ''
  importSummary.value = null

  if (!importFile.value) {
    importFieldErrors.value = { file: 'Choose a CSV or Excel file to import.' }
    return
  }

  try {
    importSummary.value = await importProductsMutation.mutateAsync(importFile.value)
    const hasFailures = importSummary.value.failed > 0

    toast.success(hasFailures ? 'Product import blocked.' : 'Product import completed.', {
      description: hasFailures
        ? `No changes were applied because ${importSummary.value.failed} row${importSummary.value.failed === 1 ? '' : 's'} failed validation.`
        : `Created ${importSummary.value.created}, updated ${importSummary.value.updated}, failed ${importSummary.value.failed}.`,
    })
  } catch (error: unknown) {
    const normalized = normalizeApiError(error)
    importFieldErrors.value = mapFieldErrors(normalized.fieldErrors)
    importSubmitError.value = normalized.fieldErrors ? '' : normalized.message
  }
}

const triggerDownload = (blob: Blob, filename: string) => {
  const objectUrl = window.URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = objectUrl
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  document.body.removeChild(anchor)
  window.URL.revokeObjectURL(objectUrl)
}

const exportWithFormat = async (format: ProductExportFormat) => {
  exportSubmitError.value = ''

  try {
    const result = await exportProductsMutation.mutateAsync({
      format,
      q: debouncedSearch.value || undefined,
      is_active: statusFilter.value === 'all' ? undefined : statusFilter.value === 'active',
    })

    triggerDownload(result.blob, result.filename)
    toast.success(`Product export started (${format.toUpperCase()}).`, {
      description: 'Your download should begin automatically.',
    })
  } catch (error: unknown) {
    exportSubmitError.value = normalizeApiError(error).message
  }
}
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Products"
      description="Manage catalog records, archive SKUs, and handle catalog imports or exports."
    >
      <template #actions>
        <div class="flex flex-wrap items-center justify-end gap-2">
          <Button
            v-if="canImportProducts"
            variant="outline"
            size="sm"
            data-test="products-open-import"
            @click="importDialogOpen = true"
          >
            <Upload data-icon="inline-start" />
            Import CSV/XLSX
          </Button>

          <DropdownMenu v-if="canExportProducts">
            <DropdownMenuTrigger as-child>
              <Button variant="outline" size="sm" data-test="products-open-export">
                <Download data-icon="inline-start" />
                Export
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-40">
              <DropdownMenuGroup>
                <DropdownMenuItem data-test="products-export-csv" @select="exportWithFormat('csv')">
                  Export CSV
                </DropdownMenuItem>
                <DropdownMenuItem
                  data-test="products-export-xlsx"
                  @select="exportWithFormat('xlsx')"
                >
                  Export XLSX
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>

          <Button
            v-if="canManageProducts"
            size="sm"
            data-test="products-open-create"
            @click="openCreateDialog"
          >
            <Plus data-icon="inline-start" />
            Create Product
          </Button>
        </div>
      </template>
    </PageHeader>

    <Card class="gap-0">
      <CardHeader class="pb-4">
        <CardTitle class="text-base">Search & Filters</CardTitle>
        <CardDescription
          >Search by SKU or product name, then narrow the catalog by product
          status.</CardDescription
        >
      </CardHeader>
      <CardContent class="flex flex-col gap-3">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
          <div class="relative w-full xl:min-w-[360px]">
            <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
            <Input
              v-model="searchInput"
              class="pl-9"
              placeholder="Search by SKU or name…"
              name="products_search"
              autocomplete="off"
              spellcheck="false"
              aria-label="Search products"
              data-test="products-search"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 xl:justify-end">
            <Select v-model="statusFilter">
              <SelectTrigger class="w-[200px]" data-test="products-status-filter">
                <SelectValue placeholder="Product status" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem value="all">All products</SelectItem>
                  <SelectItem value="active">Active only</SelectItem>
                  <SelectItem value="archived">Archived only</SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>

            <Button variant="outline" class="min-w-23" @click="resetFilters">Reset</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="productsQuery.error.value" message="Failed to load products." />
    <ApiErrorAlert v-if="exportSubmitError" :message="exportSubmitError" />
    <ApiErrorAlert v-if="productMutationError" :message="productMutationError" />

    <EmptyStateCard
      v-if="!productsQuery.isLoading.value && products.length === 0"
      title="No products"
      description="No products match the current filters."
    />

    <Card v-else class="pb-3">
      <CardContent>
        <ProductsDataTable
          v-if="meta"
          :rows="products"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total-rows="meta.total"
          :per-page="meta.per_page"
          :can-manage-products="canManageProducts"
          @view="openProductDetail"
          @edit="openProductEdit"
          @archive="openArchiveDialog"
          @update:page="(nextPage) => (page = nextPage)"
          @update:per-page="(nextPerPage) => (perPage = nextPerPage)"
        />
      </CardContent>
    </Card>

    <Dialog v-model:open="isProductDetailDialogOpen">
      <DialogContent class="sm:max-w-2xl" data-test="products-detail-dialog">
        <DialogHeader>
          <DialogTitle>Product Detail</DialogTitle>
          <DialogDescription>Review product metadata, price, and stock context.</DialogDescription>
        </DialogHeader>

        <ApiErrorAlert
          v-if="detailProductQuery.error.value"
          message="Unable to load product details."
        />

        <div v-else-if="isDetailDialogLoading" class="text-muted-foreground text-sm">
          Loading product details…
        </div>

        <div v-else-if="detailProductForDialog" class="space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
              <h3 class="text-lg font-semibold">{{ detailProductForDialog.name }}</h3>
              <p class="text-muted-foreground text-sm">SKU {{ detailProductForDialog.sku }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <StatusBadge :status="detailProductForDialog.is_active ? 'active' : 'archived'" />
              <Button
                v-if="canManageProducts"
                variant="outline"
                size="sm"
                data-test="products-detail-edit"
                @click="openProductEdit(detailProductForDialog.id)"
              >
                Edit
              </Button>
              <Button
                v-if="canManageProducts && detailProductForDialog.is_active"
                variant="outline"
                size="sm"
                data-test="products-detail-archive"
                @click="openArchiveDialog(detailProductForDialog.id)"
              >
                Archive
              </Button>
            </div>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <Card class="gap-0">
              <CardHeader class="pb-3 gap-0">
                <CardTitle class="text-base">Catalog Snapshot</CardTitle>
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <p>
                  <span class="font-medium">Sale price:</span>
                  {{ formatCurrency(detailProductForDialog.sale_price) }}
                </p>
                <p>
                  <span class="font-medium">Description:</span>
                  {{ detailProductForDialog.description ?? '-' }}
                </p>
                <p>
                  <span class="font-medium">Created:</span>
                  {{ formatDateTime(detailProductForDialog.created_at) }}
                </p>
                <p>
                  <span class="font-medium">Updated:</span>
                  {{ formatDateTime(detailProductForDialog.updated_at) }}
                </p>
              </CardContent>
            </Card>

            <Card class="gap-0">
              <CardHeader class="pb-3">
                <CardTitle class="text-base">Stock Context</CardTitle>
                <CardDescription>
                  Inventory summary is shown when the user can access stock data.
                </CardDescription>
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <template v-if="canViewInventory">
                  <div v-if="detailStockQuery.isLoading.value" class="text-muted-foreground">
                    Loading stock summary…
                  </div>
                  <template v-else-if="detailStockSummary">
                    <p>
                      <span class="font-medium">On hand:</span> {{ detailStockSummary.qty_on_hand }}
                    </p>
                    <p>
                      <span class="font-medium">Reserved:</span>
                      {{ detailStockSummary.qty_reserved }}
                    </p>
                    <p>
                      <span class="font-medium">Available:</span> {{ detailStockSummary.available }}
                    </p>
                  </template>
                  <p v-else class="text-muted-foreground">No stock row found for this product.</p>
                </template>
                <p v-else class="text-muted-foreground">
                  You do not have access to inventory stock details.
                </p>
              </CardContent>
            </Card>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="isProductFormDialogOpen">
      <DialogContent class="sm:max-w-2xl" data-test="products-form-dialog">
        <DialogHeader>
          <DialogTitle>{{ isEditRoute ? 'Edit Product' : 'Create Product' }}</DialogTitle>
          <DialogDescription>
            {{
              isEditRoute
                ? 'Update the product metadata and status.'
                : 'Add a new catalog product and initialize its stock row.'
            }}
          </DialogDescription>
        </DialogHeader>

        <ApiErrorAlert v-if="productMutationError" :message="productMutationError" />

        <ProductForm
          v-model="productForm"
          :field-errors="productFieldErrors"
          :mode="isEditRoute ? 'edit' : 'create'"
          :pending="isProductFormPending"
          @cancel="isProductFormDialogOpen = false"
          @submit="submitProductForm"
          @field-blur="onProductFieldBlur"
          @field-input="onProductFieldInput"
          @generate-sku="onGenerateProductSku"
        />
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="importDialogOpen">
      <DialogContent class="sm:max-w-xl" data-test="products-import-dialog">
        <DialogHeader>
          <DialogTitle>Import Products</DialogTitle>
          <DialogDescription>
            Upload a CSV or Excel file to create or update products. Existing filters stay intact
            after import.
          </DialogDescription>
        </DialogHeader>

        <ApiErrorAlert v-if="importSubmitError" :message="importSubmitError" />

        <form
          class="flex flex-col gap-4"
          data-test="products-import-form"
          @submit.prevent="submitImport"
        >
          <FieldGroup class="gap-4">
            <Field>
              <FieldLabel for="products-import-file">CSV or Excel file</FieldLabel>
              <Input
                id="products-import-file"
                type="file"
                accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                data-test="products-import-file"
                @change="onImportFileChange"
              />
              <FieldError v-if="importFieldErrors.file" :errors="[importFieldErrors.file]" />
            </Field>
          </FieldGroup>

          <Alert v-if="importSummary">
            <AlertTitle>Import summary</AlertTitle>
            <AlertDescription>
              <template v-if="importSummary.failed > 0">
                No changes were applied. {{ importSummary.failed }} out of
                {{ importSummary.total_rows }} rows failed validation.
              </template>
              <template v-else>
                Created {{ importSummary.created }}, updated {{ importSummary.updated }}, failed
                {{ importSummary.failed }} out of {{ importSummary.total_rows }} rows.
              </template>
            </AlertDescription>
          </Alert>

          <Card v-if="importSummary && importSummary.errors.length > 0" class="gap-0">
            <CardHeader class="pb-3">
              <CardTitle class="text-base">Row errors</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
              <p
                v-for="error in importSummary.errors"
                :key="`${error.row}-${error.message}`"
                class="text-destructive"
              >
                Row {{ error.row }}: {{ error.message }}
              </p>
            </CardContent>
          </Card>

          <div class="flex items-center justify-end gap-2">
            <Button type="button" variant="outline" @click="importDialogOpen = false">Close</Button>
            <Button
              type="submit"
              :disabled="importProductsMutation.isPending.value"
              data-test="products-import-submit"
            >
              {{ importProductsMutation.isPending.value ? 'Importing...' : 'Import products' }}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="archiveDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Archive product</AlertDialogTitle>
          <AlertDialogDescription>
            Archived products remain visible in the catalog but can no longer be used as active
            items.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <ApiErrorAlert v-if="productMutationError" :message="productMutationError" />
        <AlertDialogFooter>
          <AlertDialogCancel :disabled="archiveProductMutation.isPending.value"
            >Cancel</AlertDialogCancel
          >
          <Button
            variant="destructive"
            :disabled="archiveProductMutation.isPending.value"
            data-test="products-archive-confirm"
            @click="onArchiveProduct"
          >
            {{ archiveProductMutation.isPending.value ? 'Archiving...' : 'Archive' }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </section>
</template>
