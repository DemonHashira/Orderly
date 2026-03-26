<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { formatCurrency } from '@/lib/formatters'
import { orderUpsertSchema } from '@/features/orders/validation/order-upsert.schema'
import type { OrderUpsertPayload } from '@/features/orders/types'
import type { Customer, LookupOrderCreate, Order } from '@/types'

type FormItem = {
  product_id: string
  quantity: string
  unit_price: string
}

const props = withDefaults(
  defineProps<{
    mode: 'create' | 'edit'
    initialOrder?: Order | null
    customers: Customer[]
    lookups: LookupOrderCreate | null
    isSubmitting?: boolean
    isDisabled?: boolean
    apiError?: string
    serverFieldErrors?: Record<string, string>
  }>(),
  {
    initialOrder: null,
    isSubmitting: false,
    isDisabled: false,
    apiError: '',
    serverFieldErrors: () => ({}),
  },
)

const emit = defineEmits<{
  (e: 'submit', payload: OrderUpsertPayload): void
  (e: 'cancel'): void
}>()

const state = reactive({
  customer_id: '',
  sales_channel_id: '',
  internal_notes: '',
  items: [{ product_id: '', quantity: '1', unit_price: '' }] as FormItem[],
})

const localErrors = reactive<Record<string, string>>({})
const dismissedServerErrorKeys = ref<Set<string>>(new Set())

const customerFieldId = 'order-customer'
const customerFieldErrorId = 'order-customer-error'
const salesChannelFieldId = 'order-sales-channel'
const salesChannelFieldErrorId = 'order-sales-channel-error'
const internalNotesFieldId = 'order-internal-notes'
const itemsFieldErrorId = 'order-items-error'

const combinedFieldErrors = computed(() => {
  const filteredServerErrors = Object.fromEntries(
    Object.entries(props.serverFieldErrors).filter(
      ([key]) => !dismissedServerErrorKeys.value.has(key),
    ),
  )

  return {
    ...filteredServerErrors,
    ...localErrors,
  }
})

const productSalePriceById = computed(() => {
  return new Map(
    (props.lookups?.products ?? []).map(
      (product) => [String(product.id), product.sale_price] as const,
    ),
  )
})

const submitLabel = computed(() => (props.mode === 'create' ? 'Create Order' : 'Save Changes'))
const itemCount = computed(() => state.items.length)

const orderTotal = computed(() => {
  return state.items.reduce((sum, item) => {
    const quantity = Number.parseInt(item.quantity, 10)
    const unitPrice = Number.parseFloat(item.unit_price)

    const safeQuantity = Number.isFinite(quantity) && quantity > 0 ? quantity : 0
    const safeUnitPrice = Number.isFinite(unitPrice) && unitPrice >= 0 ? unitPrice : 0

    return sum + safeQuantity * safeUnitPrice
  }, 0)
})

watch(
  () => props.initialOrder,
  (order) => {
    setInitialStateFromOrder(order)
  },
  { immediate: true },
)

const onSubmit = () => {
  clearErrors()

  const candidate = {
    customer_id: Number(state.customer_id),
    sales_channel_id: Number(state.sales_channel_id),
    internal_notes: state.internal_notes.trim() === '' ? null : state.internal_notes.trim(),
    items: state.items.map((item) => ({
      product_id: Number(item.product_id),
      quantity: Number(item.quantity),
      unit_price: item.unit_price.trim(),
    })),
  }

  const parsed = orderUpsertSchema.safeParse(candidate)
  if (!parsed.success) {
    parsed.error.issues.forEach((issue) => {
      const key = issue.path.join('.')
      if (!localErrors[key]) {
        localErrors[key] = issue.message
      }
    })
    return
  }

  const payload: OrderUpsertPayload = {
    customer_id: parsed.data.customer_id,
    sales_channel_id: parsed.data.sales_channel_id,
    internal_notes: parsed.data.internal_notes ?? null,
    items: parsed.data.items.map((item) => ({
      product_id: item.product_id,
      quantity: item.quantity,
      unit_price: item.unit_price?.trim() ? item.unit_price.trim() : null,
    })),
  }

  emit('submit', payload)
}

const clearErrors = () => {
  Object.keys(localErrors).forEach((key) => {
    delete localErrors[key]
  })
  dismissedServerErrorKeys.value = new Set()
}

const clearFieldError = (key: string) => {
  delete localErrors[key]
  const next = new Set(dismissedServerErrorKeys.value)
  next.add(key)
  dismissedServerErrorKeys.value = next
}

const addItem = () => {
  state.items.push({
    product_id: '',
    quantity: '1',
    unit_price: '',
  })
  clearFieldError('items')
}

const removeItem = (index: number) => {
  if (state.items.length <= 1) {
    return
  }

  state.items.splice(index, 1)
  clearFieldError('items')
}

const onProductChange = (index: number, productId: string) => {
  const item = state.items[index]
  if (!item) {
    return
  }

  item.product_id = productId
  item.unit_price = productSalePriceById.value.get(productId) ?? ''
  clearFieldError(`items.${index}.product_id`)
  clearFieldError(`items.${index}.unit_price`)
  clearFieldError('items')
}

const getItemProductFieldId = (index: number) => `order-item-${index}-product`
const getItemProductFieldErrorId = (index: number) => `order-item-${index}-product-error`
const getItemQuantityFieldId = (index: number) => `order-item-${index}-quantity`
const getItemQuantityFieldErrorId = (index: number) => `order-item-${index}-quantity-error`
const getItemUnitPriceFieldId = (index: number) => `order-item-${index}-unit-price`
const getItemUnitPriceFieldErrorId = (index: number) => `order-item-${index}-unit-price-error`

function setInitialStateFromOrder(order: Order | null) {
  if (!order) {
    state.customer_id = ''
    state.sales_channel_id = ''
    state.internal_notes = ''
    state.items = [{ product_id: '', quantity: '1', unit_price: '' }]
    return
  }

  state.customer_id = String(order.customer_id)
  state.sales_channel_id = String(order.sales_channel_id)
  state.internal_notes = order.internal_notes ?? ''
  state.items = order.items?.map((item) => ({
    product_id: String(item.product_id),
    quantity: String(item.quantity),
    unit_price: item.unit_price ? String(item.unit_price) : '',
  })) ?? [{ product_id: '', quantity: '1', unit_price: '' }]
}
</script>

<template>
  <Card class="mx-auto flex w-full max-w-4xl flex-col gap-0 shadow-sm">
    <CardHeader class="pb-4">
      <CardTitle>{{ mode === 'create' ? 'New Order' : 'Edit Draft Order' }}</CardTitle>
      <CardDescription>
        Choose customer, channel, and line items. Prices can be overridden per item if needed.
      </CardDescription>
    </CardHeader>
    <CardContent>
      <form class="flex flex-col gap-5" @submit.prevent="onSubmit">
        <div
          v-if="apiError"
          class="rounded-md border border-destructive/20 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {{ apiError }}
        </div>

        <FieldGroup class="gap-4 md:grid md:grid-cols-2">
          <Field
            :data-invalid="Boolean(combinedFieldErrors.customer_id) || undefined"
            :data-disabled="isDisabled || undefined"
          >
            <FieldLabel :for="customerFieldId">Customer</FieldLabel>
            <Select
              v-model="state.customer_id"
              :disabled="isDisabled"
              @update:model-value="() => clearFieldError('customer_id')"
            >
              <SelectTrigger
                :id="customerFieldId"
                class="w-full"
                :aria-invalid="Boolean(combinedFieldErrors.customer_id)"
                :aria-describedby="
                  combinedFieldErrors.customer_id ? customerFieldErrorId : undefined
                "
              >
                <SelectValue placeholder="Select customer" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="customer in customers"
                    :key="customer.id"
                    :value="String(customer.id)"
                  >
                    {{ customer.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <FieldError
              v-if="combinedFieldErrors.customer_id"
              :id="customerFieldErrorId"
              :errors="[combinedFieldErrors.customer_id]"
            />
          </Field>

          <Field
            :data-invalid="Boolean(combinedFieldErrors.sales_channel_id) || undefined"
            :data-disabled="isDisabled || undefined"
          >
            <FieldLabel :for="salesChannelFieldId">Sales Channel</FieldLabel>
            <Select
              v-model="state.sales_channel_id"
              :disabled="isDisabled"
              @update:model-value="() => clearFieldError('sales_channel_id')"
            >
              <SelectTrigger
                :id="salesChannelFieldId"
                class="w-full"
                :aria-invalid="Boolean(combinedFieldErrors.sales_channel_id)"
                :aria-describedby="
                  combinedFieldErrors.sales_channel_id ? salesChannelFieldErrorId : undefined
                "
              >
                <SelectValue placeholder="Select channel" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="channel in lookups?.sales_channels ?? []"
                    :key="channel.id"
                    :value="String(channel.id)"
                  >
                    {{ channel.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <FieldError
              v-if="combinedFieldErrors.sales_channel_id"
              :id="salesChannelFieldErrorId"
              :errors="[combinedFieldErrors.sales_channel_id]"
            />
          </Field>
        </FieldGroup>

        <Field :data-disabled="isDisabled || undefined">
          <FieldLabel :for="internalNotesFieldId">Internal Notes</FieldLabel>
          <Input
            :id="internalNotesFieldId"
            v-model="state.internal_notes"
            :disabled="isDisabled"
            placeholder="Optional notes for this draft"
            class="w-full"
          />
        </Field>

        <div class="flex flex-col gap-3">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-medium">Items</h3>
            <Button
              type="button"
              variant="outline"
              size="sm"
              :disabled="isDisabled"
              @click="addItem"
            >
              <Plus data-icon="inline-start" aria-hidden="true" />
              Add Item
            </Button>
          </div>

          <div
            class="max-h-73.75 flex flex-col gap-3 overflow-y-auto pr-1 [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border"
          >
            <div v-for="(item, index) in state.items" :key="index" class="rounded-md border p-3">
              <FieldGroup class="gap-3 md:grid md:grid-cols-[minmax(0,1fr)_96px_156px_36px]">
                <Field
                  :data-invalid="
                    Boolean(combinedFieldErrors[`items.${index}.product_id`]) || undefined
                  "
                  :data-disabled="isDisabled || undefined"
                >
                  <FieldLabel :for="getItemProductFieldId(index)">Product</FieldLabel>
                  <Select
                    :model-value="item.product_id"
                    :disabled="isDisabled"
                    @update:model-value="(value) => onProductChange(index, String(value ?? ''))"
                  >
                    <SelectTrigger
                      :id="getItemProductFieldId(index)"
                      class="w-full"
                      :aria-invalid="Boolean(combinedFieldErrors[`items.${index}.product_id`])"
                      :aria-describedby="
                        combinedFieldErrors[`items.${index}.product_id`]
                          ? getItemProductFieldErrorId(index)
                          : undefined
                      "
                    >
                      <SelectValue placeholder="Select product" />
                    </SelectTrigger>
                    <SelectContent side="bottom" align="start" :side-offset="6" class="max-h-64">
                      <SelectGroup>
                        <SelectItem
                          v-for="product in lookups?.products ?? []"
                          :key="product.id"
                          :value="String(product.id)"
                        >
                          {{ product.sku }} - {{ product.name }}
                        </SelectItem>
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                  <FieldError
                    v-if="combinedFieldErrors[`items.${index}.product_id`]"
                    :id="getItemProductFieldErrorId(index)"
                    :errors="[combinedFieldErrors[`items.${index}.product_id`]]"
                  />
                </Field>

                <Field
                  :data-invalid="
                    Boolean(combinedFieldErrors[`items.${index}.quantity`]) || undefined
                  "
                  :data-disabled="isDisabled || undefined"
                >
                  <FieldLabel :for="getItemQuantityFieldId(index)">Qty</FieldLabel>
                  <Input
                    :id="getItemQuantityFieldId(index)"
                    v-model="item.quantity"
                    type="number"
                    min="1"
                    :disabled="isDisabled"
                    :aria-invalid="Boolean(combinedFieldErrors[`items.${index}.quantity`])"
                    :aria-describedby="
                      combinedFieldErrors[`items.${index}.quantity`]
                        ? getItemQuantityFieldErrorId(index)
                        : undefined
                    "
                    @update:model-value="
                      () => {
                        clearFieldError(`items.${index}.quantity`)
                        clearFieldError('items')
                      }
                    "
                  />
                  <FieldError
                    v-if="combinedFieldErrors[`items.${index}.quantity`]"
                    :id="getItemQuantityFieldErrorId(index)"
                    :errors="[combinedFieldErrors[`items.${index}.quantity`]]"
                  />
                </Field>

                <Field
                  :data-invalid="
                    Boolean(combinedFieldErrors[`items.${index}.unit_price`]) || undefined
                  "
                  data-disabled
                >
                  <FieldLabel :for="getItemUnitPriceFieldId(index)">Unit Price</FieldLabel>
                  <Input
                    :id="getItemUnitPriceFieldId(index)"
                    :model-value="item.unit_price || ''"
                    placeholder="Auto from product"
                    disabled
                    readonly
                    tabindex="-1"
                    aria-readonly="true"
                    :aria-describedby="
                      combinedFieldErrors[`items.${index}.unit_price`]
                        ? getItemUnitPriceFieldErrorId(index)
                        : undefined
                    "
                    class="pointer-events-none"
                  />
                  <FieldError
                    v-if="combinedFieldErrors[`items.${index}.unit_price`]"
                    :id="getItemUnitPriceFieldErrorId(index)"
                    :errors="[combinedFieldErrors[`items.${index}.unit_price`]]"
                  />
                </Field>

                <div class="flex items-start md:pt-7">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="isDisabled || state.items.length <= 1"
                    :aria-label="`Remove item ${index + 1}`"
                    @click="removeItem(index)"
                  >
                    <Trash2 aria-hidden="true" />
                  </Button>
                </div>
              </FieldGroup>
            </div>
          </div>

          <Field v-if="combinedFieldErrors.items" data-invalid>
            <FieldError :id="itemsFieldErrorId" :errors="[combinedFieldErrors.items]" />
          </Field>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t pt-4">
          <div
            class="bg-muted/40 flex w-full flex-wrap items-center justify-between gap-3 rounded-md border px-3 py-2"
          >
            <p class="text-muted-foreground text-sm">
              {{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }} in this order
            </p>
            <div class="flex items-baseline gap-2">
              <span class="text-muted-foreground text-sm font-medium">Order Total</span>
              <span class="text-lg font-semibold tabular-nums">{{
                formatCurrency(orderTotal)
              }}</span>
            </div>
          </div>

          <Button type="button" variant="outline" :disabled="isSubmitting" @click="emit('cancel')">
            Cancel
          </Button>
          <Button
            type="submit"
            :disabled="isSubmitting || isDisabled"
            data-test="order-form-submit"
          >
            <Plus
              v-if="mode === 'create' && !isSubmitting"
              data-icon="inline-start"
              aria-hidden="true"
            />
            {{ isSubmitting ? 'Saving...' : submitLabel }}
          </Button>
        </div>
      </form>
    </CardContent>
  </Card>
</template>
