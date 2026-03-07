<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
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

const setInitialStateFromOrder = (order: Order | null) => {
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

watch(
  () => props.initialOrder,
  (order) => {
    setInitialStateFromOrder(order)
  },
  { immediate: true },
)

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
</script>

<template>
  <Card class="mx-auto flex w-full max-w-4xl flex-col gap-0 shadow-sm">
    <CardHeader class="pb-4">
      <CardTitle>{{ mode === 'create' ? 'New Order' : 'Edit Draft Order' }}</CardTitle>
      <CardDescription>
        Choose customer, channel, and line items. Prices can be overridden per item if needed.
      </CardDescription>
    </CardHeader>
    <CardContent class="space-y-5">
      <div
        v-if="apiError"
        class="bg-destructive/10 text-destructive rounded-md border border-destructive/20 px-3 py-2 text-sm"
      >
        {{ apiError }}
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <label class="block text-sm font-medium">Customer</label>
          <Select
            v-model="state.customer_id"
            :disabled="isDisabled"
            @update:model-value="() => clearFieldError('customer_id')"
          >
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select customer" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="customer in customers"
                :key="customer.id"
                :value="String(customer.id)"
              >
                {{ customer.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="combinedFieldErrors.customer_id" class="text-destructive text-sm">
            {{ combinedFieldErrors.customer_id }}
          </p>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium">Sales Channel</label>
          <Select
            v-model="state.sales_channel_id"
            :disabled="isDisabled"
            @update:model-value="() => clearFieldError('sales_channel_id')"
          >
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select channel" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="channel in lookups?.sales_channels ?? []"
                :key="channel.id"
                :value="String(channel.id)"
              >
                {{ channel.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="combinedFieldErrors.sales_channel_id" class="text-destructive text-sm">
            {{ combinedFieldErrors.sales_channel_id }}
          </p>
        </div>
      </div>

      <div class="space-y-2">
        <label for="internal_notes" class="block text-sm font-medium">Internal Notes</label>
        <Input
          id="internal_notes"
          v-model="state.internal_notes"
          :disabled="isDisabled"
          placeholder="Optional notes for this draft"
          class="w-full"
        />
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-medium">Items</h3>
          <Button type="button" variant="outline" size="sm" :disabled="isDisabled" @click="addItem">
            <Plus class="mr-1 size-4" />
            Add Item
          </Button>
        </div>

        <div
          class="max-h-73.75 space-y-3 overflow-y-auto pr-1 [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border"
        >
          <div v-for="(item, index) in state.items" :key="index" class="rounded-md border p-3">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_96px_156px_36px] md:items-start">
              <div class="space-y-2">
                <label class="block text-sm font-medium">Product</label>
                <Select
                  :model-value="item.product_id"
                  :disabled="isDisabled"
                  @update:model-value="(value) => onProductChange(index, String(value ?? ''))"
                >
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select product" />
                  </SelectTrigger>
                  <SelectContent side="bottom" align="start" :side-offset="6" class="max-h-64">
                    <SelectItem
                      v-for="product in lookups?.products ?? []"
                      :key="product.id"
                      :value="String(product.id)"
                    >
                      {{ product.sku }} - {{ product.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p
                  v-if="combinedFieldErrors[`items.${index}.product_id`]"
                  class="text-destructive text-sm"
                >
                  {{ combinedFieldErrors[`items.${index}.product_id`] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="block text-sm font-medium">Qty</label>
                <Input
                  v-model="item.quantity"
                  type="number"
                  min="1"
                  :disabled="isDisabled"
                  @update:model-value="
                    () => {
                      clearFieldError(`items.${index}.quantity`)
                      clearFieldError('items')
                    }
                  "
                />
                <p
                  v-if="combinedFieldErrors[`items.${index}.quantity`]"
                  class="text-destructive text-sm"
                >
                  {{ combinedFieldErrors[`items.${index}.quantity`] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="block text-sm font-medium">Unit Price</label>
                <Input
                  :model-value="item.unit_price || ''"
                  placeholder="Auto from product"
                  disabled
                  readonly
                  tabindex="-1"
                  aria-readonly="true"
                  class="pointer-events-none"
                />
                <p
                  v-if="combinedFieldErrors[`items.${index}.unit_price`]"
                  class="text-destructive text-sm"
                >
                  {{ combinedFieldErrors[`items.${index}.unit_price`] }}
                </p>
              </div>

              <Button
                type="button"
                variant="ghost"
                size="icon"
                class="self-start md:mt-8"
                :disabled="isDisabled || state.items.length <= 1"
                @click="removeItem(index)"
              >
                <Trash2 class="size-4" />
              </Button>
            </div>
          </div>
        </div>

        <p v-if="combinedFieldErrors.items" class="text-destructive text-sm">
          {{ combinedFieldErrors.items }}
        </p>
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
            <span class="text-lg font-semibold tabular-nums">{{ formatCurrency(orderTotal) }}</span>
          </div>
        </div>

        <Button type="button" variant="outline" :disabled="isSubmitting" @click="emit('cancel')">
          Cancel
        </Button>
        <Button type="button" :disabled="isSubmitting || isDisabled" @click="onSubmit">
          {{ isSubmitting ? 'Saving...' : submitLabel }}
        </Button>
      </div>
    </CardContent>
  </Card>
</template>
