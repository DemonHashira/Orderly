<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import type { LoginPayload } from '@/types/auth'
import { useLoginMutation } from '@/features/auth/composables/useLoginMutation'
import PasswordInput from '@/features/auth/ui/PasswordInput.vue'
import { loginSchema } from '@/features/auth/validation/login.schema'
import { normalizeApiError } from '@/shared/api/errors'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const router = useRouter()
const route = useRoute()
const loginMutation = useLoginMutation()
const formError = ref('')

const schema = toTypedSchema(loginSchema as never)

const { defineField, errors, handleSubmit, setErrors, values, setFieldValue } = useForm({
  validationSchema: schema,
  initialValues: {
    email: '',
    password: '',
    remember: false,
  },
})

const [email, emailAttrs] = defineField('email', {
  validateOnBlur: true,
  validateOnChange: false,
  validateOnInput: false,
  validateOnModelUpdate: false,
})
const [password, passwordAttrs] = defineField('password')

const rememberModel = computed({
  get: () => Boolean(values.remember),
  set: (value: boolean | 'indeterminate') => setFieldValue('remember', value === true),
})

const isSafeInternalRedirect = (value: string): boolean => {
  return value.startsWith('/') && !value.startsWith('//')
}

const isInvalidCredentialError = (error: ReturnType<typeof normalizeApiError>): boolean => {
  if (error.status === 401) {
    return true
  }

  const messages = error.fieldErrors?.email ?? []
  return messages.some((message) => message.toLowerCase().includes('invalid credential'))
}

const onSubmit = handleSubmit(async (values) => {
  formError.value = ''

  try {
    await loginMutation.mutateAsync(values as LoginPayload)

    const redirectTarget =
      typeof route.query.redirect === 'string' && isSafeInternalRedirect(route.query.redirect)
        ? route.query.redirect
        : '/dashboard'

    await router.push(redirectTarget)
  } catch (error: unknown) {
    const normalizedError = normalizeApiError(error)
    const hasCredentialError = isInvalidCredentialError(normalizedError)

    if (hasCredentialError) {
      setErrors({
        email: undefined,
        password: undefined,
      })
      formError.value = 'Login failed. Check your email and password, then try again.'
      return
    }

    if (normalizedError.fieldErrors) {
      const mappedErrors = Object.fromEntries(
        Object.entries(normalizedError.fieldErrors).map(([key, messages]) => [
          key,
          messages?.[0] ?? '',
        ]),
      )

      setErrors(mappedErrors)
      formError.value = ''
      return
    }

    formError.value = normalizedError.message
  }
})
</script>

<template>
  <form
    :class="cn('animate-in fade-in-0 zoom-in-95 duration-500 flex flex-col gap-4', props.class)"
    @submit.prevent="onSubmit"
  >
    <FieldGroup class="gap-3">
      <div class="flex flex-col items-center gap-1 text-center">
        <h1 class="text-2xl font-bold">Login to your account</h1>
        <p class="text-muted-foreground text-sm text-balance">
          Enter your credentials to access Orderly
        </p>
      </div>
      <div
        v-if="formError"
        class="bg-destructive/10 text-destructive rounded-md border border-destructive/20 px-3 py-2 text-sm"
      >
        {{ formError }}
      </div>
      <Field>
        <FieldLabel for="email"> Email </FieldLabel>
        <Input
          id="email"
          v-model="email"
          v-bind="emailAttrs"
          type="email"
          placeholder="m@example.com"
          class="transition-all duration-200"
          :aria-invalid="Boolean(errors.email)"
          required
        />
        <FieldError v-if="errors.email" :errors="[errors.email]" />
      </Field>
      <Field>
        <FieldLabel for="password"> Password </FieldLabel>
        <PasswordInput
          id="password"
          v-model="password"
          v-bind="passwordAttrs"
          placeholder="Enter your password"
          class="transition-all duration-200"
          :aria-invalid="Boolean(errors.password)"
          required
        />
        <FieldError v-if="errors.password" :errors="[errors.password]" />
      </Field>
      <label class="flex items-center gap-2 text-sm font-medium">
        <Checkbox id="remember" v-model="rememberModel" />
        Remember me
      </label>
      <Field>
        <Button
          type="submit"
          class="w-full transition-all duration-200 hover:shadow-md"
          :disabled="loginMutation.isPending.value"
        >
          Login
        </Button>
      </Field>
    </FieldGroup>
  </form>
</template>
