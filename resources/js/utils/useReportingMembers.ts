import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';
import { api, type Member } from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useMembersQuery } from '@/utils/useMembersQuery';
import { canViewMembers, canViewTeamTimeEntries } from '@/utils/permissions';

/** What the reporting needs to know about a member: filter by id, show and group by name. */
export type ReportingMember = Pick<Member, 'id' | 'user_id' | 'name'>;

/**
 * The members the current user can report on: everyone for users who can view members,
 * the members of their teams for employees in a team, nobody otherwise.
 */
export function useReportingMembers() {
    const canViewAll = canViewMembers();
    // Only users who may list all members ask for them; for everyone else that request is a 403.
    const allMembers = canViewAll ? useMembersQuery().members : computed<Member[]>(() => []);

    const teamMembersQuery = useQuery({
        queryKey: computed(() => ['team-members', getCurrentOrganizationId()]),
        queryFn: async () => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) throw new Error('No organization');
            const response = await api.getTeamMembers({
                params: { organization: organizationId },
            });
            return response.data;
        },
        enabled: () => !canViewAll && canViewTeamTimeEntries() && !!getCurrentOrganizationId(),
        staleTime: 1000 * 30,
    });

    const members = computed<ReportingMember[]>(() => {
        if (canViewAll) {
            return allMembers.value;
        }
        return teamMembersQuery.data.value ?? [];
    });

    return { members };
}
