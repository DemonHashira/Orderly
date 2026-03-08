import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminUsersKeys } from '@/lib/query-keys'
import type {
  CreateAdminUserPayload,
  UpdateAdminUserMutationPayload,
  UpdateAdminUserRoleMutationPayload,
  UpdateAdminUserStatusMutationPayload,
} from '@/features/admin-users/types'
import {
  createAdminUser,
  fetchAdminRoles,
  fetchAdminUsers,
  updateAdminUser,
  updateAdminUserRole,
  updateAdminUserStatus,
} from '@/features/admin-users/api/admin-users.api'
import type { AdminUsersListParams } from '@/types'

export const useAdminUsersQuery = (params: MaybeRefOrGetter<AdminUsersListParams>) => {
  const queryParams = computed(() => toValue(params))

  return useQuery({
    queryKey: computed(() => adminUsersKeys.list(queryParams.value)),
    queryFn: () => fetchAdminUsers(queryParams.value),
  })
}

export const useAdminRolesQuery = (enabled: MaybeRefOrGetter<boolean> = true) =>
  useQuery({
    queryKey: adminUsersKeys.roles(),
    queryFn: fetchAdminRoles,
    enabled: computed(() => Boolean(toValue(enabled))),
  })

export const useCreateAdminUserMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateAdminUserPayload) => createAdminUser(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: adminUsersKeys.all })
    },
  })
}

export const useUpdateAdminUserMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: UpdateAdminUserMutationPayload) => updateAdminUser(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: adminUsersKeys.all })
    },
  })
}

export const useUpdateAdminUserStatusMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, isActive }: UpdateAdminUserStatusMutationPayload) =>
      updateAdminUserStatus(id, { is_active: isActive }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: adminUsersKeys.all })
    },
  })
}

export const useUpdateAdminUserRoleMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, role }: UpdateAdminUserRoleMutationPayload) =>
      updateAdminUserRole(id, { role }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: adminUsersKeys.all })
    },
  })
}
