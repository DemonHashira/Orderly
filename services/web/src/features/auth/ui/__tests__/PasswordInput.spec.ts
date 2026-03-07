import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import PasswordInput from '../PasswordInput.vue'

describe('PasswordInput', () => {
  it('toggles password visibility', async () => {
    const Host = defineComponent({
      components: { PasswordInput },
      setup() {
        const value = ref('Secret-123!')
        return { value }
      },
      template: `<PasswordInput id="password" v-model="value" />`,
    })

    const wrapper = mount(Host)

    const input = wrapper.find('#password')
    expect(input.attributes('type')).toBe('password')

    const toggle = wrapper.find('button[type="button"]')
    await toggle.trigger('click')
    expect(input.attributes('type')).toBe('text')

    await toggle.trigger('click')
    expect(input.attributes('type')).toBe('password')
  })
})
