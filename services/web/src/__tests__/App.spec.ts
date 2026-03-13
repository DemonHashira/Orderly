import { describe, it, expect } from 'vitest'

import { mount } from '@vue/test-utils'
import App from '../App.vue'

describe('App', () => {
  it('renders the global toaster in a neutral top-right layout', () => {
    const wrapper = mount(App, {
      global: {
        stubs: {
          RouterView: true,
          Toaster: {
            name: 'Toaster',
            template: '<div data-test="toaster" />',
            props: {
              position: { type: String, required: false },
              closeButton: { type: Boolean, required: false },
              richColors: { type: Boolean, required: false },
            },
          },
        },
      },
    })

    const toaster = wrapper.getComponent({ name: 'Toaster' })

    expect(wrapper.exists()).toBe(true)
    expect(toaster.props('position')).toBe('top-right')
    expect(toaster.props('closeButton')).toBe(true)
    expect(toaster.props('richColors')).toBe(false)
  })
})
