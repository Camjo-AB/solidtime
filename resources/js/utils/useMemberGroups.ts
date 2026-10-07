import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed } from 'vue';
import { api, type CreateMemberGroupBody, type MemberGroup } from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { fetchAllPages } from '@/utils/fetchAllPages';
import { useNotificationsStore } from '@/utils/notification';

/** Member groups are called "teams" in the UI. */
export function useMemberGroups() {
    const queryClient = useQueryClient();
    const { handleApiRequestNotifications } = useNotificationsStore();

    const query = useQuery({
        queryKey: computed(() => ['member-groups', getCurrentOrganizationId()]),
        queryFn: async () => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) throw new Error('No organization');
            return fetchAllPages((page) =>
                api.getMemberGroups({
                    params: { organization: organizationId },
                    queries: { page },
                })
            );
        },
        enabled: () => !!getCurrentOrganizationId(),
    });

    const memberGroups = computed<MemberGroup[]>(() => query.data.value ?? []);

    function invalidate() {
        queryClient.invalidateQueries({ queryKey: ['member-groups'] });
        // Who can see whom in the reporting depends on the groups.
        queryClient.invalidateQueries({ queryKey: ['team-members'] });
    }

    const { mutateAsync: createMemberGroup } = useMutation({
        mutationFn: async (body: CreateMemberGroupBody) => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) return undefined;
            return handleApiRequestNotifications(
                () => api.createMemberGroup(body, { params: { organization: organizationId } }),
                'Team created successfully',
                'Failed to create team'
            );
        },
        onSuccess: invalidate,
    });

    const { mutateAsync: updateMemberGroup } = useMutation({
        mutationFn: async ({ id, body }: { id: string; body: CreateMemberGroupBody }) => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) return undefined;
            return handleApiRequestNotifications(
                () =>
                    api.updateMemberGroup(body, {
                        params: { organization: organizationId, memberGroup: id },
                    }),
                'Team updated successfully',
                'Failed to update team'
            );
        },
        onSuccess: invalidate,
    });

    const { mutateAsync: deleteMemberGroup } = useMutation({
        mutationFn: async (id: string) => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) return undefined;
            return handleApiRequestNotifications(
                () =>
                    api.deleteMemberGroup(undefined, {
                        params: { organization: organizationId, memberGroup: id },
                    }),
                'Team deleted successfully',
                'Failed to delete team'
            );
        },
        onSuccess: invalidate,
    });

    return { ...query, memberGroups, createMemberGroup, updateMemberGroup, deleteMemberGroup };
}
