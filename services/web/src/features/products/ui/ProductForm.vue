<script setup lang="ts">
import { computed } from 'vue'
import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import type {
  ProductDialogForm,
  ProductDialogMode,
  ProductFormField,
} from '@/features/products/types'

const props = defineProps<{
  modelValue: ProductDialogForm
  fieldErrors: Record<string, string>
  mode: ProductDialogMode
  pending: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: ProductDialogForm): void
  (e: 'submit'): void
  (e: 'cancel'): void
  (e: 'field-blur', field: ProductFormField): void
  (e: 'field-input', field: ProductFormField): void
  (e: 'generate-sku'): void
}>()

const formModel = computed({
  get: () => props.modelValue,
  set: (value: ProductDialogForm) => emit('update:modelValue', value),
})

const updateField = <T extends keyof ProductDialogForm>(field: T, value: ProductDialogForm[T]) => {
  formModel.value = {
    ...formModel.value,
    [field]: value,
  }

  emit('field-input', field)
}
</script>

<template>
  <form class="flex flex-col gap-4" data-test="products-form" @submit.prevent="emit('submit')">
    <FieldGroup class="gap-4">
      <Field :data-invalid="Boolean(fieldErrors.sku) || undefined">
        <div class="flex items-center justify-between gap-2">
          <FieldLabel for="product-sku">SKU</FieldLabel>
          <Button
            type="button"
            variant="outline"
            size="sm"
            data-test="products-form-generate-sku"
            @click="emit('generate-sku')"
          >
            Use name
          </Button>
        </div>
        <Input
          id="product-sku"
          :model-value="formModel.sku"
          autocomplete="off"
          data-test="products-form-sku"
          :aria-invalid="Boolean(fieldErrors.sku)"
          @update:model-value="(value) => updateField('sku', String(value ?? ''))"
          @blur="emit('field-blur', 'sku')"
        />
        <FieldError v-if="fieldErrors.sku" :errors="[fieldErrors.sku]" />
      </Field>

      <Field :data-invalid="Boolean(fieldErrors.name) || undefined">
        <FieldLabel for="product-name">Name</FieldLabel>
        <Input
          id="product-name"
          :model-value="formModel.name"
          autocomplete="off"
          data-test="products-form-name"
          :aria-invalid="Boolean(fieldErrors.name)"
          @update:model-value="(value) => updateField('name', String(value ?? ''))"
          @blur="emit('field-blur', 'name')"
        />
        <FieldError v-if="fieldErrors.name" :errors="[fieldErrors.name]" />
      </Field>

      <Field :data-invalid="Boolean(fieldErrors.sale_price) || undefined">
        <FieldLabel for="product-sale-price">Sale Price</FieldLabel>
        <Input
          id="product-sale-price"
          :model-value="formModel.sale_price"
          type="number"
          step="0.01"
          min="0"
          inputmode="decimal"
          data-test="products-form-sale-price"
          :aria-invalid="Boolean(fieldErrors.sale_price)"
          @update:model-value="(value) => updateField('sale_price', String(value ?? ''))"
          @blur="emit('field-blur', 'sale_price')"
        />
        <FieldError v-if="fieldErrors.sale_price" :errors="[fieldErrors.sale_price]" />
      </Field>

      <Field :data-invalid="Boolean(fieldErrors.description) || undefined">
        <FieldLabel for="product-description">Description</FieldLabel>
        <Input
          id="product-description"
          :model-value="formModel.description"
          autocomplete="off"
          data-test="products-form-description"
          :aria-invalid="Boolean(fieldErrors.description)"
          @update:model-value="(value) => updateField('description', String(value ?? ''))"
          @blur="emit('field-blur', 'description')"
        />
        <FieldError v-if="fieldErrors.description" :errors="[fieldErrors.description]" />
      </Field>

      <Field class="gap-2" :data-invalid="Boolean(fieldErrors.is_active) || undefined">
        <label class="flex items-center gap-2 text-sm font-medium">
          <Checkbox
            id="product-active"
            :model-value="formModel.is_active"
            data-test="products-form-active"
            @update:model-value="(value) => updateField('is_active', Boolean(value))"
            @blur="emit('field-blur', 'is_active')"
          />
          Active product
        </label>
        <FieldError v-if="fieldErrors.is_active" :errors="[fieldErrors.is_active]" />
      </Field>
    </FieldGroup>

    <div class="flex items-center justify-end gap-2">
      <Button type="button" variant="outline" @click="emit('cancel')">Cancel</Button>
      <Button
        type="button"
        :disabled="pending"
        data-test="products-form-submit"
        @click="emit('submit')"
      >
        <Plus v-if="!pending && mode === 'create'" data-icon="inline-start" />
        {{ pending ? 'Saving...' : mode === 'edit' ? 'Save changes' : 'Create product' }}
      </Button>
    </div>
  </form>
</template>
