import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { VueQueryPlugin } from '@tanstack/vue-query'
import App from './App.vue'
import router from '@/app/router'
import { queryClient } from '@/lib/query-client'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

pinia.use(createPersistedState())

app.use(pinia)
app.use(router)
app.use(VueQueryPlugin, { queryClient })

app.mount('#app')
