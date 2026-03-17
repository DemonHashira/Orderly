<script setup lang="ts">
import { computed } from 'vue'
import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import type { CustomerDialogForm, CustomerDialogMode } from '@/features/customers/types'

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
}>()

const formModel = computed({
  get: () => props.modelValue,
  set: (value: CustomerDialogForm) => emit('update:modelValue', value),
})

const updateField = <T extends keyof CustomerDialogForm>(
  field: T,
  value: CustomerDialogForm[T],
) => {
  formModel.value = {
    ...formModel.value,
    [field]: value,
  }
}
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
            :aria-invalid="Boolean(fieldErrors.first_name)"
            @update:model-value="(value) => updateField('first_name', String(value ?? ''))"
          />
          <FieldError v-if="fieldErrors.first_name" :errors="[fieldErrors.first_name]" />
        </Field>

        <Field>
          <FieldLabel for="customer-middle-name">Middle name</FieldLabel>
          <Input
            id="customer-middle-name"
            :model-value="formModel.middle_name"
            autocomplete="additional-name"
            data-test="customers-form-middle-name"
            :aria-invalid="Boolean(fieldErrors.middle_name)"
            @update:model-value="(value) => updateField('middle_name', String(value ?? ''))"
          />
          <FieldError v-if="fieldErrors.middle_name" :errors="[fieldErrors.middle_name]" />
        </Field>
      </div>

      <Field>
        <FieldLabel for="customer-last-name">Last name</FieldLabel>
        <Input
          id="customer-last-name"
          :model-value="formModel.last_name"
          autocomplete="family-name"
          data-test="customers-form-last-name"
          :aria-invalid="Boolean(fieldErrors.last_name)"
          @update:model-value="(value) => updateField('last_name', String(value ?? ''))"
        />
        <FieldError v-if="fieldErrors.last_name" :errors="[fieldErrors.last_name]" />
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
            :aria-invalid="Boolean(fieldErrors.phone)"
            @update:model-value="(value) => updateField('phone', String(value ?? ''))"
          />
          <FieldError v-if="fieldErrors.phone" :errors="[fieldErrors.phone]" />
        </Field>

        <Field>
          <FieldLabel for="customer-email">Email</FieldLabel>
          <Input
            id="customer-email"
            :model-value="formModel.email"
            type="email"
            autocomplete="email"
            data-test="customers-form-email"
            :aria-invalid="Boolean(fieldErrors.email)"
            @update:model-value="(value) => updateField('email', String(value ?? ''))"
          />
          <FieldError v-if="fieldErrors.email" :errors="[fieldErrors.email]" />
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
