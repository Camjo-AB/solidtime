import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';
import { api, type Client, type Project, type Task } from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { useClientsQuery } from '@/utils/useClientsQuery';
import { canViewAllTimeEntries, canViewTeamTimeEntries } from '@/utils/permissions';

function mergeById<T extends { id: string }>(own: T[], extra: T[]): T[] {
    const ownIds = new Set(own.map((item) => item.id));
    return [...own, ...extra.filter((item) => !ownIds.has(item.id))];
}

/**
 * Projects, tasks and clients for the reporting. Employees in a team only have access to their
 * own projects, so the ones from their teammates' time entries are added to show their names.
 */
export function useReportingEntities() {
    const { projects: ownProjects } = useProjectsQuery();
    const { tasks: ownTasks } = useTasksQuery();
    const { clients: ownClients } = useClientsQuery();

    const teamEntitiesQuery = useQuery({
        queryKey: computed(() => ['team-entities', getCurrentOrganizationId()]),
        queryFn: async () => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) throw new Error('No organization');
            const response = await api.getTeamEntities({
                params: { organization: organizationId },
            });
            return response.data;
        },
        enabled: () =>
            !canViewAllTimeEntries() && canViewTeamTimeEntries() && !!getCurrentOrganizationId(),
        staleTime: 1000 * 30,
    });

    const projects = computed<Project[]>(() =>
        mergeById(ownProjects.value, teamEntitiesQuery.data.value?.projects ?? [])
    );
    const tasks = computed<Task[]>(() =>
        mergeById(ownTasks.value, teamEntitiesQuery.data.value?.tasks ?? [])
    );
    const clients = computed<Client[]>(() =>
        mergeById(ownClients.value, teamEntitiesQuery.data.value?.clients ?? [])
    );

    return { projects, tasks, clients };
}
