<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { useChangePasswordMutation } from '@/features/auth/composables/useChangePasswordMutation'
import PasswordInput from '@/features/auth/ui/PasswordInput.vue'
import { changePasswordSchema } from '@/features/auth/validation/change-password.schema'
import { normalizeApiError } from '@/shared/api/errors'
import type { ChangePasswordPayload } from '@/types/auth'

const formError = ref('')
const successMessage = ref('')
const mutation = useChangePasswordMutation()

const schema = toTypedSchema(changePasswordSchema as never)

const { defineField, errors, handleSubmit, setErrors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: {
    current_password: '',
    password: '',
    password_confirmation: '',
  },
})

const [currentPassword, currentPasswordAttrs] = defineField('current_password')
const [password, passwordAttrs] = defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation')

const onSubmit = handleSubmit(async (values) => {
  formError.value = ''
  successMessage.value = ''

  try {
    const response = await mutation.mutateAsync(values as ChangePasswordPayload)
    successMessage.value = response?.message ?? 'Password changed successfully.'
    resetForm()
  } catch (error: unknown) {
    const normalizedError = normalizeApiError(error)

    if (normalizedError.fieldErrors) {
      const mappedErrors = Object.fromEntries(
        Object.entries(normalizedError.fieldErrors).map(([key, messages]) => [
          key,
          messages?.[0] ?? '',
        ]),
      )
      setErrors(mappedErrors)
      return
    }

    formError.value = normalizedError.message
  }
})
</script>

<template>
  <form class="w-full max-w-xl" @submit.prevent="onSubmit">
    <FieldGroup class="gap-4">
      <div
        v-if="successMessage"
        class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700"
      >
        {{ successMessage }}
      </div>
      <div
        v-if="formError"
        class="bg-destructive/10 text-destructive rounded-md border border-destructive/20 px-3 py-2 text-sm"
      >
        {{ formError }}
      </div>

      <Field>
        <FieldLabel for="current_password">Current password</FieldLabel>
        <PasswordInput
          id="current_password"
          v-model="currentPassword"
          v-bind="currentPasswordAttrs"
          autocomplete="current-password"
          :aria-invalid="Boolean(errors.current_password)"
          required
        />
        <FieldError v-if="errors.current_password" :errors="[errors.current_password]" />
      </Field>

      <Field>
        <FieldLabel for="password">New password</FieldLabel>
        <PasswordInput
          id="password"
          v-model="password"
          v-bind="passwordAttrs"
          autocomplete="new-password"
          :aria-invalid="Boolean(errors.password)"
          required
        />
        <FieldError v-if="errors.password" :errors="[errors.password]" />
      </Field>

      <Field>
        <FieldLabel for="password_confirmation">Confirm new password</FieldLabel>
        <PasswordInput
          id="password_confirmation"
          v-model="passwordConfirmation"
          v-bind="passwordConfirmationAttrs"
          autocomplete="new-password"
          :aria-invalid="Boolean(errors.password_confirmation)"
          required
        />
        <FieldError v-if="errors.password_confirmation" :errors="[errors.password_confirmation]" />
      </Field>

      <Field>
        <Button type="submit" :disabled="mutation.isPending.value">
          {{ mutation.isPending.value ? 'Saving...' : 'Change password' }}
        </Button>
      </Field>
    </FieldGroup>
  </form>
</template>
