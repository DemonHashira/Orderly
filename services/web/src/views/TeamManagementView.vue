<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Plus } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  useAdminRolesQuery,
  useAdminUsersQuery,
  useCreateAdminUserMutation,
  useUpdateAdminUserMutation,
  useUpdateAdminUserRoleMutation,
  useUpdateAdminUserStatusMutation,
} from '@/features/admin-users/composables/useAdminUsersQueries'
import {
  createEmptyTeamMemberDialogForm,
  type TeamMemberDialogMode,
  type UserFormField,
  USER_FORM_FIELDS_TO_VALIDATE,
} from '@/features/admin-users/types'
import {
  validateTeamMemberDialogField,
  validateTeamMemberDialogForm,
} from '@/features/admin-users/validation/team-member.schema'
import { useAuth } from '@/features/auth/composables/useAuth'
import { usePermission } from '@/features/auth/composables/usePermission'
import { formatDateTime } from '@/lib/formatters'
import { normalizeApiError } from '@/shared/api/errors'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useInitialLoadingGate } from '@/shared/composables/useInitialLoadingGate'
import {
  ApiErrorAlert,
  ConfirmActionDialog,
  DebouncedSearchInput,
  EmptyStateCard,
  PageHeader,
  PageInitialSkeleton,
  PageRefetchOverlay,
  ServerPagination,
} from '@/shared/ui'
import { BASIC_LIST_FIELDS, useListUiStateStore } from '@/stores/list-ui-state'
import type { AdminUser } from '@/types'

const route = useRoute()
const router = useRouter()
const listUiStore = useListUiStateStore()
const { user: currentUser } = useAuth()
const canManageUsers = usePermission('users.manage')
const canManageRoles = usePermission('roles.manage')
const listModule = 'team_users' as const
const isSyncingFromRoute = ref(false)

const page = computed({
  get: () => listUiStore.modules[listModule].page,
  set: (value: number) => listUiStore.setState(listModule, { page: value }),
})
const search = computed({
  get: () => listUiStore.modules[listModule].q,
  set: (value: string) => listUiStore.setState(listModule, { q: value }),
})
const debouncedSearch = useDebouncedRef(search)

const usersQuery = useAdminUsersQuery(
  computed(() => ({
    page: page.value,
    per_page: 15,
    q: debouncedSearch.value,
  })),
)
const rolesQuery = useAdminRolesQuery(canManageRoles)
const createUserMutation = useCreateAdminUserMutation()
const updateUserMutation = useUpdateAdminUserMutation()
const updateStatusMutation = useUpdateAdminUserStatusMutation()
const updateRoleMutation = useUpdateAdminUserRoleMutation()

const isUserDialogOpen = ref(false)
const dialogMode = ref<TeamMemberDialogMode>('create')
const editingUserId = ref<number | null>(null)
const submitError = ref('')
const fieldErrors = ref<Record<string, string>>({})
const clientFieldErrors = ref<Record<string, string>>({})
const rowActionError = ref('')
const roleDraftByUserId = reactive<Record<number, string>>({})

const touchedFields = reactive<Record<UserFormField, boolean>>({
  first_name: false,
  middle_name: false,
  last_name: false,
  email: false,
  password: false,
  password_confirmation: false,
  role: false,
})

const userForm = ref(createEmptyTeamMemberDialogForm())

const users = computed(() => usersQuery.data.value?.data ?? [])
const meta = computed(() => usersQuery.data.value?.meta)
const availableRoles = computed(() => rolesQuery.data.value?.data ?? [])
const isInitialLoading = useInitialLoadingGate(usersQuery.isLoading)
const isRefreshing = computed(() => !isInitialLoading.value && usersQuery.isFetching.value)
const isSubmittingUser = computed(
  () => createUserMutation.isPending.value || updateUserMutation.isPending.value,
)
const isRoleUpdatePending = computed(() => updateRoleMutation.isPending.value)
const activeUsersCount = computed(() => users.value.filter((teamUser) => teamUser.is_active).length)
const inactiveUsersCount = computed(() => users.value.length - activeUsersCount.value)
const pendingRoleChangesCount = computed(
  () =>
    users.value.filter((teamUser) => {
      const draftRole = roleDraftByUserId[teamUser.id]
      return draftRole && draftRole !== teamUser.role
    }).length,
)

const resetUserForm = () => {
  userForm.value = createEmptyTeamMemberDialogForm(availableRoles.value[0]?.name ?? '')
}

const resetFieldValidation = () => {
  clientFieldErrors.value = {}
  ;(Object.keys(touchedFields) as UserFormField[]).forEach((field) => {
    touchedFields[field] = false
  })
}

const setClientFieldError = (field: UserFormField, message: string | null) => {
  if (message) {
    clientFieldErrors.value[field] = message
    return
  }

  delete clientFieldErrors.value[field]
}

const validateField = (field: UserFormField) => {
  const message = validateTeamMemberDialogField(userForm.value, field, {
    mode: dialogMode.value,
    canManageRoles: canManageRoles.value,
  })
  setClientFieldError(field, message)
}

const onFieldBlur = (field: UserFormField) => {
  touchedFields[field] = true
  validateField(field)
}

const validateDialogForm = () => {
  const nextErrors = validateTeamMemberDialogForm(userForm.value, {
    mode: dialogMode.value,
    canManageRoles: canManageRoles.value,
  })
  clientFieldErrors.value = nextErrors

  USER_FORM_FIELDS_TO_VALIDATE.forEach((field) => {
    touchedFields[field] = true
  })

  return Object.keys(nextErrors).length === 0
}

const getFieldError = (field: UserFormField) =>
  clientFieldErrors.value[field] ?? fieldErrors.value[field]

const mapFieldErrors = (errors?: Record<string, string[]>) => {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages?.[0] ?? 'Invalid value']),
  )
}

const openCreateDialog = () => {
  dialogMode.value = 'create'
  editingUserId.value = null
  submitError.value = ''
  fieldErrors.value = {}
  resetFieldValidation()
  resetUserForm()
  isUserDialogOpen.value = true
}

const openEditDialog = (targetUser: AdminUser) => {
  dialogMode.value = 'edit'
  editingUserId.value = targetUser.id
  submitError.value = ''
  fieldErrors.value = {}
  resetFieldValidation()
  userForm.value = {
    first_name: targetUser.first_name ?? '',
    middle_name: targetUser.middle_name ?? '',
    last_name: targetUser.last_name ?? '',
    email: targetUser.email,
    password: '',
    password_confirmation: '',
    role: targetUser.role ?? availableRoles.value[0]?.name ?? '',
    is_active: targetUser.is_active,
  }
  isUserDialogOpen.value = true
}

const onDialogOpenChange = (open: boolean) => {
  isUserDialogOpen.value = open

  if (!open) {
    submitError.value = ''
    fieldErrors.value = {}
    resetFieldValidation()
  }
}

const submitUserForm = async () => {
  submitError.value = ''
  fieldErrors.value = {}
  clientFieldErrors.value = {}
  rowActionError.value = ''

  if (!validateDialogForm()) {
    return
  }

  try {
    if (dialogMode.value === 'create') {
      await createUserMutation.mutateAsync({
        first_name: userForm.value.first_name,
        middle_name: userForm.value.middle_name || null,
        last_name: userForm.value.last_name,
        email: userForm.value.email,
        password: userForm.value.password,
        password_confirmation: userForm.value.password_confirmation,
        is_active: userForm.value.is_active,
        ...(canManageRoles.value && userForm.value.role.length > 0
          ? { role: userForm.value.role }
          : {}),
      })
      toast.success('Team member created successfully.')
    } else if (editingUserId.value != null) {
      await updateUserMutation.mutateAsync({
        id: editingUserId.value,
        payload: {
          first_name: userForm.value.first_name,
          middle_name: userForm.value.middle_name || null,
          last_name: userForm.value.last_name,
          email: userForm.value.email,
          ...(userForm.value.password
            ? {
                password: userForm.value.password,
                password_confirmation: userForm.value.password_confirmation,
              }
            : {}),
        },
      })

      if (canManageRoles.value && userForm.value.role.length > 0) {
        await updateRoleMutation.mutateAsync({
          id: editingUserId.value,
          role: userForm.value.role,
        })
      }

      toast.success('Team member updated successfully.')
    }

    isUserDialogOpen.value = false
  } catch (error: unknown) {
    const normalizedError = normalizeApiError(error)
    fieldErrors.value = mapFieldErrors(normalizedError.fieldErrors)
    submitError.value = normalizedError.fieldErrors ? '' : normalizedError.message
  }
}

const updateUserStatus = async (targetUser: AdminUser, isActive: boolean) => {
  rowActionError.value = ''

  try {
    await updateStatusMutation.mutateAsync({
      id: targetUser.id,
      isActive,
    })
    toast.success(`Team member ${isActive ? 'activated' : 'deactivated'} successfully.`)
  } catch (error: unknown) {
    rowActionError.value = normalizeApiError(error).message
  }
}

const applyRoleChange = async (targetUser: AdminUser) => {
  if (!canManageRoles.value) {
    return
  }

  const role = roleDraftByUserId[targetUser.id]
  if (!role || role === targetUser.role) {
    return
  }

  rowActionError.value = ''

  try {
    await updateRoleMutation.mutateAsync({
      id: targetUser.id,
      role,
    })
    toast.success('Team role updated successfully.')
  } catch (error: unknown) {
    rowActionError.value = normalizeApiError(error).message
  }
}

watch(
  () => route.query,
  (query) => {
    const normalizedQuery = query as Record<string, unknown>
    if (!listUiStore.hasRelevantQuery(normalizedQuery, BASIC_LIST_FIELDS)) {
      const persisted = listUiStore.toQuery(listModule, BASIC_LIST_FIELDS)
      if (Object.keys(persisted).length > 0) {
        void router.replace({ query: persisted })
        return
      }
    }

    isSyncingFromRoute.value = true
    listUiStore.hydrateFromQuery(listModule, normalizedQuery, BASIC_LIST_FIELDS)
    isSyncingFromRoute.value = false
  },
  { immediate: true },
)

watch(search, () => {
  if (!isSyncingFromRoute.value) {
    page.value = 1
  }
})

watch([debouncedSearch, page], () => {
  if (isSyncingFromRoute.value) {
    return
  }

  const nextQuery = {
    ...listUiStore.toQuery(listModule, BASIC_LIST_FIELDS),
    ...(debouncedSearch.value ? { q: debouncedSearch.value } : {}),
  }
  const currentQuery = listUiStore.normalizeQuery(
    listModule,
    route.query as Record<string, unknown>,
    BASIC_LIST_FIELDS,
  )

  if (JSON.stringify(nextQuery) === JSON.stringify(currentQuery)) {
    return
  }

  void router.replace({
    query: nextQuery,
  })
})

watch(
  users,
  (nextUsers) => {
    nextUsers.forEach((nextUser) => {
      roleDraftByUserId[nextUser.id] = nextUser.role ?? ''
    })
  },
  { immediate: true },
)

watch(
  [availableRoles, canManageRoles],
  ([nextRoles, hasRolePermission]) => {
    if (!hasRolePermission || nextRoles.length === 0) {
      return
    }

    if (userForm.value.role.length === 0) {
      userForm.value.role = nextRoles[0]?.name ?? ''
    }
  },
  { immediate: true },
)
</script>

<template>
  <PageInitialSkeleton v-if="isInitialLoading" />

  <section v-else class="relative space-y-4">
    <PageRefetchOverlay :show="isRefreshing" />
    <PageHeader
      title="Team Management"
      description="Create staff accounts, manage activation, and assign operational roles."
    >
      <template #actions>
        <Button
          v-if="canManageUsers"
          size="sm"
          data-test="team-open-create"
          @click="openCreateDialog"
        >
          <Plus data-icon="inline-start" />
          Add Team Member
        </Button>
      </template>
    </PageHeader>

    <Card>
      <CardContent class="space-y-4">
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <span class="rounded-full bg-muted px-2 py-1 font-medium">
            {{ users.length }} total
          </span>
          <span class="rounded-full bg-emerald-100 px-2 py-1 font-medium text-emerald-700">
            {{ activeUsersCount }} active
          </span>
          <span class="rounded-full bg-zinc-200 px-2 py-1 font-medium text-zinc-700">
            {{ inactiveUsersCount }} inactive
          </span>
          <span
            v-if="pendingRoleChangesCount > 0"
            class="rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-700"
          >
            {{ pendingRoleChangesCount }} unsaved role changes
          </span>
        </div>
        <DebouncedSearchInput v-model="search" placeholder="Search team members by name or email" />
      </CardContent>
    </Card>

    <ApiErrorAlert v-if="usersQuery.error.value" message="Failed to load team members." />
    <ApiErrorAlert v-if="rolesQuery.error.value" message="Failed to load assignable roles." />
    <ApiErrorAlert v-if="rowActionError" :message="rowActionError" />

    <EmptyStateCard
      v-if="!usersQuery.isLoading.value && users.length === 0"
      title="No team members"
      description="Create your first user to start assigning operational responsibilities."
    />

    <Card v-else>
      <CardContent class="space-y-2">
        <div class="flex items-center justify-between text-sm text-muted-foreground">
          <span>Showing {{ users.length }} team members</span>
          <span v-if="meta">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Role</TableHead>
              <TableHead>Created</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="teamUser in users" :key="teamUser.id">
              <TableCell class="font-medium">{{ teamUser.name }}</TableCell>
              <TableCell>{{ teamUser.email }}</TableCell>
              <TableCell>
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="
                    teamUser.is_active
                      ? 'bg-emerald-100 text-emerald-700'
                      : 'bg-zinc-200 text-zinc-700'
                  "
                >
                  {{ teamUser.is_active ? 'Active' : 'Inactive' }}
                </span>
              </TableCell>
              <TableCell>
                <div v-if="canManageRoles" class="flex items-center gap-2">
                  <Select
                    :model-value="roleDraftByUserId[teamUser.id]"
                    @update:model-value="
                      (value) =>
                        (roleDraftByUserId[teamUser.id] = value == null ? '' : String(value))
                    "
                  >
                    <SelectTrigger class="h-9 min-w-[220px]">
                      <SelectValue placeholder="Select role" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="roleOption in availableRoles"
                        :key="roleOption.name"
                        :value="roleOption.name"
                      >
                        {{ roleOption.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <Button
                    size="sm"
                    variant="outline"
                    :disabled="isRoleUpdatePending"
                    @click="applyRoleChange(teamUser)"
                  >
                    Save
                  </Button>
                </div>
                <span v-else>{{ teamUser.role ?? 'Unassigned' }}</span>
              </TableCell>
              <TableCell>{{ formatDateTime(teamUser.created_at) }}</TableCell>
              <TableCell class="space-x-2 text-right">
                <Button variant="outline" size="sm" @click="openEditDialog(teamUser)">Edit</Button>
                <ConfirmActionDialog
                  v-if="teamUser.is_active"
                  title="Deactivate team member"
                  description="Inactive users cannot access the workspace until reactivated."
                  confirm-label="Deactivate"
                  @confirm="updateUserStatus(teamUser, false)"
                >
                  <template #trigger>
                    <Button
                      variant="outline"
                      size="sm"
                      :disabled="
                        teamUser.id === currentUser?.id || updateStatusMutation.isPending.value
                      "
                    >
                      Deactivate
                    </Button>
                  </template>
                </ConfirmActionDialog>
                <Button
                  v-else
                  variant="outline"
                  size="sm"
                  :disabled="updateStatusMutation.isPending.value"
                  @click="updateUserStatus(teamUser, true)"
                >
                  Activate
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>

    <ServerPagination
      v-if="meta"
      :current-page="meta.current_page"
      :total-pages="meta.last_page"
      @update:page="(nextPage) => (page = nextPage)"
    />

    <Dialog :open="isUserDialogOpen" @update:open="onDialogOpenChange">
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{{
            dialogMode === 'create' ? 'Add Team Member' : 'Edit Team Member'
          }}</DialogTitle>
          <DialogDescription>
            {{
              dialogMode === 'create'
                ? 'Create an account and assign an operational role.'
                : 'Update account details and role assignment.'
            }}
          </DialogDescription>
        </DialogHeader>

        <ApiErrorAlert v-if="submitError" :message="submitError" />

        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitUserForm">
          <div class="space-y-2">
            <Label for="first-name">First name</Label>
            <Input
              id="first-name"
              v-model="userForm.first_name"
              required
              @blur="onFieldBlur('first_name')"
            />
            <p v-if="getFieldError('first_name')" class="text-xs text-destructive">
              {{ getFieldError('first_name') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="middle-name">Middle name</Label>
            <Input
              id="middle-name"
              v-model="userForm.middle_name"
              @blur="onFieldBlur('middle_name')"
            />
            <p v-if="getFieldError('middle_name')" class="text-xs text-destructive">
              {{ getFieldError('middle_name') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="last-name">Last name</Label>
            <Input
              id="last-name"
              v-model="userForm.last_name"
              required
              @blur="onFieldBlur('last_name')"
            />
            <p v-if="getFieldError('last_name')" class="text-xs text-destructive">
              {{ getFieldError('last_name') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input
              id="email"
              v-model="userForm.email"
              required
              type="email"
              @blur="onFieldBlur('email')"
            />
            <p v-if="getFieldError('email')" class="text-xs text-destructive">
              {{ getFieldError('email') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="password">Password {{ dialogMode === 'edit' ? '(optional)' : '' }}</Label>
            <Input
              id="password"
              v-model="userForm.password"
              :required="dialogMode === 'create'"
              type="password"
              @blur="onFieldBlur('password')"
            />
            <p v-if="getFieldError('password')" class="text-xs text-destructive">
              {{ getFieldError('password') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="password-confirmation">Password confirmation</Label>
            <Input
              id="password-confirmation"
              v-model="userForm.password_confirmation"
              :required="dialogMode === 'create' || userForm.password.length > 0"
              type="password"
              @blur="onFieldBlur('password_confirmation')"
            />
            <p v-if="getFieldError('password_confirmation')" class="text-xs text-destructive">
              {{ getFieldError('password_confirmation') }}
            </p>
          </div>

          <div v-if="canManageRoles" class="space-y-2 sm:col-span-2">
            <Label for="user-role">Role</Label>
            <Select
              :model-value="userForm.role"
              @update:model-value="
                (value) => {
                  userForm.role = value == null ? '' : String(value)
                  if (touchedFields.role) onFieldBlur('role')
                }
              "
            >
              <SelectTrigger id="user-role" class="h-10 w-full">
                <SelectValue placeholder="Select role" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="roleOption in availableRoles"
                  :key="roleOption.name"
                  :value="roleOption.name"
                >
                  {{ roleOption.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="getFieldError('role')" class="text-xs text-destructive">
              {{ getFieldError('role') }}
            </p>
          </div>

          <div class="flex items-center justify-end gap-2 sm:col-span-2">
            <Button type="button" variant="outline" @click="isUserDialogOpen = false"
              >Cancel</Button
            >
            <Button
              type="submit"
              :disabled="isSubmittingUser || isRoleUpdatePending"
              data-test="team-user-submit"
            >
              <Plus v-if="dialogMode === 'create' && !isSubmittingUser" data-icon="inline-start" />
              {{ dialogMode === 'create' ? 'Create User' : 'Save Changes' }}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </section>
</template>
