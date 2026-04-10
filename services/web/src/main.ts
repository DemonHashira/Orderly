import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { VueQueryPlugin } from '@tanstack/vue-query'
import vueLenis from 'lenis/vue'
import App from './App.vue'
import router from '@/app/router'
import { queryClient } from '@/lib/query-client'
import 'lenis/dist/lenis.css'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

pinia.use(
  createPersistedState({
    storage: sessionStorage,
    auto: true,
  }),
)

app.use(pinia)
app.use(router)
app.use(VueQueryPlugin, { queryClient })
app.use(vueLenis)

app.mount('#app')
