<script setup lang="ts">
import { computed } from 'vue'
import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import type {
  CustomerDialogAddressForm,
  CustomerDialogForm,
  CustomerDialogMode,
  CustomerFormField,
} from '@/features/customers/types'

const props = defineProps<{
  modelValue: CustomerDialogForm
  fieldErrors: Record<string, string>
  mode: CustomerDialogMode
  pending: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: CustomerDialogForm): void
  (e: 'cancel'): void
  (e: 'submit'): void
  (e: 'field-blur', field: CustomerFormField): void
  (e: 'field-input', field: CustomerFormField): void
}>()

const formModel = computed({
  get: () => props.modelValue,
  set: (value: CustomerDialogForm) => emit('update:modelValue', value),
})

const updateField = <T extends Exclude<keyof CustomerDialogForm, 'address'>>(
  field: T,
  value: CustomerDialogForm[T],
) => {
  formModel.value = {
    ...formModel.value,
    [field]: value,
  }

  emit('field-input', field)
}

const updateAddressField = <T extends keyof CustomerDialogAddressForm>(
  field: T,
  value: CustomerDialogAddressForm[T],
) => {
  formModel.value = {
    ...formModel.value,
    address: {
      ...formModel.value.address,
      [field]: value,
    },
  }

  emit('field-input', `address.${String(field)}` as CustomerFormField)
}

const getFieldError = (field: CustomerFormField): string | undefined => props.fieldErrors[field]
</script>

<template>
  <form class="flex flex-col gap-4" data-test="customers-form" @submit.prevent="emit('submit')">
    <FieldGroup class="gap-4">
      <div class="grid gap-4 md:grid-cols-2">
        <Field>
          <FieldLabel for="customer-first-name">First name</FieldLabel>
          <Input
            id="customer-first-name"
            :model-value="formModel.first_name"
            autocomplete="given-name"
            data-test="customers-form-first-name"
            :aria-invalid="Boolean(getFieldError('first_name'))"
            @update:model-value="(value) => updateField('first_name', String(value ?? ''))"
            @blur="emit('field-blur', 'first_name')"
          />
          <FieldError v-if="getFieldError('first_name')" :errors="[getFieldError('first_name')]" />
        </Field>

        <Field>
          <FieldLabel for="customer-middle-name">Middle name</FieldLabel>
          <Input
            id="customer-middle-name"
            :model-value="formModel.middle_name"
            autocomplete="additional-name"
            data-test="customers-form-middle-name"
            :aria-invalid="Boolean(getFieldError('middle_name'))"
            @update:model-value="(value) => updateField('middle_name', String(value ?? ''))"
            @blur="emit('field-blur', 'middle_name')"
          />
          <FieldError
            v-if="getFieldError('middle_name')"
            :errors="[getFieldError('middle_name')]"
          />
        </Field>
      </div>

      <Field>
        <FieldLabel for="customer-last-name">Last name</FieldLabel>
        <Input
          id="customer-last-name"
          :model-value="formModel.last_name"
          autocomplete="family-name"
          data-test="customers-form-last-name"
          :aria-invalid="Boolean(getFieldError('last_name'))"
          @update:model-value="(value) => updateField('last_name', String(value ?? ''))"
          @blur="emit('field-blur', 'last_name')"
        />
        <FieldError v-if="getFieldError('last_name')" :errors="[getFieldError('last_name')]" />
      </Field>

      <div class="grid gap-4 md:grid-cols-2">
        <Field>
          <FieldLabel for="customer-phone">Phone</FieldLabel>
          <Input
            id="customer-phone"
            :model-value="formModel.phone"
            type="tel"
            autocomplete="tel"
            data-test="customers-form-phone"
            :aria-invalid="Boolean(getFieldError('phone'))"
            @update:model-value="(value) => updateField('phone', String(value ?? ''))"
            @blur="emit('field-blur', 'phone')"
          />
          <FieldError v-if="getFieldError('phone')" :errors="[getFieldError('phone')]" />
        </Field>

        <Field>
          <FieldLabel for="customer-email">Email</FieldLabel>
          <Input
            id="customer-email"
            :model-value="formModel.email"
            type="email"
            autocomplete="email"
            data-test="customers-form-email"
            :aria-invalid="Boolean(getFieldError('email'))"
            @update:model-value="(value) => updateField('email', String(value ?? ''))"
            @blur="emit('field-blur', 'email')"
          />
          <FieldError v-if="getFieldError('email')" :errors="[getFieldError('email')]" />
        </Field>
      </div>

      <div class="space-y-4 pt-2">
        <div class="space-y-1">
          <p class="text-base font-semibold leading-none">Address</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <Field>
            <FieldLabel for="customer-address-country">Country</FieldLabel>
            <Input
              id="customer-address-country"
              :model-value="formModel.address.country"
              autocomplete="country-name"
              data-test="customers-form-address-country"
              :aria-invalid="Boolean(getFieldError('address.country'))"
              @update:model-value="(value) => updateAddressField('country', String(value ?? ''))"
              @blur="emit('field-blur', 'address.country')"
            />
            <FieldError
              v-if="getFieldError('address.country')"
              :errors="[getFieldError('address.country')]"
            />
          </Field>

          <Field>
            <FieldLabel for="customer-address-city">City</FieldLabel>
            <Input
              id="customer-address-city"
              :model-value="formModel.address.city"
              autocomplete="address-level2"
              data-test="customers-form-address-city"
              :aria-invalid="Boolean(getFieldError('address.city'))"
              @update:model-value="(value) => updateAddressField('city', String(value ?? ''))"
              @blur="emit('field-blur', 'address.city')"
            />
            <FieldError
              v-if="getFieldError('address.city')"
              :errors="[getFieldError('address.city')]"
            />
          </Field>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <Field>
            <FieldLabel for="customer-address-postal-code">Postal code</FieldLabel>
            <Input
              id="customer-address-postal-code"
              :model-value="formModel.address.postal_code"
              autocomplete="postal-code"
              data-test="customers-form-address-postal-code"
              :aria-invalid="Boolean(getFieldError('address.postal_code'))"
              @update:model-value="
                (value) => updateAddressField('postal_code', String(value ?? ''))
              "
              @blur="emit('field-blur', 'address.postal_code')"
            />
            <FieldError
              v-if="getFieldError('address.postal_code')"
              :errors="[getFieldError('address.postal_code')]"
            />
          </Field>
        </div>

        <Field>
          <FieldLabel for="customer-address-line1">Address line 1</FieldLabel>
          <Input
            id="customer-address-line1"
            :model-value="formModel.address.address_line1"
            autocomplete="address-line1"
            data-test="customers-form-address-line1"
            :aria-invalid="Boolean(getFieldError('address.address_line1'))"
            @update:model-value="
              (value) => updateAddressField('address_line1', String(value ?? ''))
            "
            @blur="emit('field-blur', 'address.address_line1')"
          />
          <FieldError
            v-if="getFieldError('address.address_line1')"
            :errors="[getFieldError('address.address_line1')]"
          />
        </Field>

        <Field>
          <FieldLabel for="customer-address-line2">Address line 2</FieldLabel>
          <Input
            id="customer-address-line2"
            :model-value="formModel.address.address_line2"
            autocomplete="address-line2"
            data-test="customers-form-address-line2"
            :aria-invalid="Boolean(getFieldError('address.address_line2'))"
            @update:model-value="
              (value) => updateAddressField('address_line2', String(value ?? ''))
            "
            @blur="emit('field-blur', 'address.address_line2')"
          />
          <FieldError
            v-if="getFieldError('address.address_line2')"
            :errors="[getFieldError('address.address_line2')]"
          />
        </Field>
      </div>
    </FieldGroup>

    <div class="flex items-center justify-end gap-2">
      <Button type="button" variant="outline" @click="emit('cancel')">Cancel</Button>
      <Button
        type="button"
        :disabled="pending"
        data-test="customers-form-submit"
        @click="emit('submit')"
      >
        <Plus v-if="!pending && mode === 'create'" data-icon="inline-start" />
        {{ pending ? 'Saving...' : mode === 'edit' ? 'Save changes' : 'Create customer' }}
      </Button>
    </div>
  </form>
</template>
