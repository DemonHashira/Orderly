import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import Dialog from '../Dialog.vue'
import DialogContent from '../DialogContent.vue'
import DialogDescription from '../DialogDescription.vue'
import DialogTitle from '../DialogTitle.vue'

const TestDialog = {
  components: {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
  },
  props: {
    open: {
      type: Boolean,
      required: true,
    },
  },
  template: `
    <Dialog :open="open">
      <DialogContent>
        <DialogTitle>Customer dialog</DialogTitle>
        <DialogDescription>Body scroll should be locked while this dialog is open.</DialogDescription>
        <p>Dialog body</p>
      </DialogContent>
    </Dialog>
  `,
}

afterEach(() => {
  document.documentElement.style.overflow = ''
  document.body.style.overflow = ''
  document.body.style.pointerEvents = ''
  document.body.style.paddingRight = ''
  document.body.style.marginRight = ''
  document.documentElement.style.removeProperty('--scrollbar-width')
  document.body.innerHTML = ''
})

describe('DialogContent', () => {
  it('locks body scroll while the dialog is open and restores it on close', async () => {
    const wrapper = mount(TestDialog, {
      attachTo: document.body,
      props: {
        open: true,
      },
    })

    await nextTick()
    await nextTick()

    expect(document.documentElement.style.overflow).toBe('hidden')
    expect(document.body.style.overflow).toBe('hidden')
    expect(document.body.style.pointerEvents).toBe('none')

    await wrapper.setProps({ open: false })
    await nextTick()
    await nextTick()

    expect(document.documentElement.style.overflow).toBe('')
    expect(document.body.style.overflow).toBe('')
    expect(document.body.style.pointerEvents).toBe('')

    wrapper.unmount()
  })
})
