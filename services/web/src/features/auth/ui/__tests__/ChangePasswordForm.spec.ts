import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, ref } from 'vue'
import ChangePasswordForm from '../ChangePasswordForm.vue'

const mutateAsync = vi.fn()

vi.mock('@/features/auth/composables/useChangePasswordMutation', () => ({
  useChangePasswordMutation: () => ({
    mutateAsync,
    isPending: ref(false),
  }),
}))

describe('ChangePasswordForm', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    mutateAsync.mockReset()
  })

  const mountForm = () => {
    const InputStub = defineComponent({
      props: {
        modelValue: {
          type: String,
          default: '',
        },
      },
      emits: ['update:modelValue'],
      setup(props, { attrs, emit }) {
        return () =>
          h('input', {
            ...attrs,
            value: props.modelValue,
            onInput: (event: Event) =>
              emit('update:modelValue', (event.target as HTMLInputElement).value),
          })
      },
    })

    return mount(ChangePasswordForm, {
      global: {
        stubs: {
          FieldGroup: { template: '<div><slot /></div>' },
          Field: { template: '<div><slot /></div>' },
          FieldLabel: { template: '<label><slot /></label>' },
          FieldError: {
            props: ['errors'],
            template: '<div v-if="errors && errors[0]">{{ errors[0] }}</div>',
          },
          Button: {
            template: '<button v-bind="$attrs" type="submit"><slot /></button>',
          },
          PasswordInput: InputStub,
        },
      },
    })
  }

  it('shows client-side validation for mismatched confirmation', async () => {
    const wrapper = mountForm()

    await wrapper.find('#current_password').setValue('Old-password-123!')
    await wrapper.find('#password').setValue('New-password-123!')
    await wrapper.find('#password_confirmation').setValue('Mismatch-password-123!')
    await wrapper.find('form').trigger('submit')
    await nextTick()
    await flushPromises()

    expect(mutateAsync).not.toHaveBeenCalled()
  })

  it('renders all change-password inputs and submit action', () => {
    const wrapper = mountForm()

    expect(wrapper.find('#current_password').exists()).toBe(true)
    expect(wrapper.find('#password').exists()).toBe(true)
    expect(wrapper.find('#password_confirmation').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })
})
