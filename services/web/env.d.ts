/// <reference types="vite/client" />
import 'pinia-plugin-persistedstate'

interface ImportMetaEnv {
  readonly VITE_APP_BASE_URL: string
  readonly VITE_API_BASE_URL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
