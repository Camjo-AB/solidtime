<script setup lang="ts">
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import { storeToRefs } from 'pinia';
import { computed } from 'vue';
import dayjs from 'dayjs';
import { formatDuration } from '@/packages/ui/src/utils/time';
import TimeTrackerStartStop from '@/packages/ui/src/TimeTrackerStartStop.vue';
import TimeTrackerProjectTaskDropdown from '@/packages/ui/src/TimeTracker/TimeTrackerProjectTaskDropdown.vue';
import TimeTrackerTagDropdown from '@/packages/ui/src/TimeTracker/TimeTrackerTagDropdown.vue';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useClientsQuery } from '@/utils/useClientsQuery';
import { useTagsStore } from '@/utils/useTags';
import { useProjectsStore } from '@/utils/useProjects';
import { useClientsStore } from '@/utils/useClients';
import { useOrganizationQuery } from '@/utils/useOrganizationQuery';
import { getOrganizationCurrencyString } from '@/utils/money';
import { isAllowedToPerformPremiumAction } from '@/utils/billing';
import { canCreateProjects } from '@/utils/permissions';
import type { CreateClientBody, CreateProjectBody, Project, Tag } from '@/packages/api/src';

const store = useCurrentTimeEntryStore();
const { currentTimeEntry, now, isActive, isOnBreak } = storeToRefs(store);
const { setActiveState } = store;

const { projects } = useProjectsQuery();
const { tasks } = useTasksQuery();
const { tags } = useTagsQuery();
const { clients } = useClientsQuery();
const { organization } = useOrganizationQuery(getCurrentOrganizationId()!);

const currentTime = computed(() => {
    if (now.value && currentTimeEntry.value.start) {
        const startTime = dayjs(currentTimeEntry.value.start);
        const diff = now.value.diff(startTime, 's');
        return formatDuration(diff);
    }
    return formatDuration(0);
});

const isRunningInDifferentOrganization = computed(() => {
    return (
        currentTimeEntry.value.organization_id &&
        getCurrentOrganizationId() &&
        currentTimeEntry.value.organization_id !== getCurrentOrganizationId()
    );
});

// Same entry as the time tracker on the time page: picking here sets what play starts with,
// and while the timer runs it updates the running entry.
function updateTimeEntry() {
    if (currentTimeEntry.value.id) {
        store.updateTimer();
    }
}

function updateProject() {
    // Adopt the billable default of the picked project, like the time tracker does
    const project = projects.value.find((p) => p.id === currentTimeEntry.value.project_id);
    if (project) {
        currentTimeEntry.value.billable = project.is_billable;
    }
    updateTimeEntry();
}

async function createProject(project: CreateProjectBody): Promise<Project | undefined> {
    const newProject = await useProjectsStore().createProject(project);
    if (newProject) {
        currentTimeEntry.value.project_id = newProject.id;
    }
    return newProject;
}

async function createClient(client: CreateClientBody) {
    return await useClientsStore().createClient(client);
}

async function createTag(tag: string): Promise<Tag | undefined> {
    return await useTagsStore().createTag(tag);
}
</script>

<template>
    <div class="pt-3 pb-2.5 px-2 relative">
        <div
            v-if="isRunningInDifferentOrganization"
            class="absolute inset-0 backdrop-blur-sm z-10 flex items-center justify-center">
            <div
                class="w-full h-[calc(100%+10px)] absolute bg-default-background opacity-75 backdrop-blur-sm"></div>
            <div class="flex space-x-3 items-center w-full z-20 justify-center">
                <span class="text-xs text-center text-text-primary">
                    The Timer is running in a different organization.
                </span>
            </div>
        </div>
        <div class="flex justify-between items-center">
            <div>
                <div class="text-text-secondary font-medium text-xs">Current Timer</div>
                <div class="text-text-primary font-medium text-base">
                    {{ currentTime }}
                </div>
            </div>
            <TimeTrackerStartStop
                :active="isActive"
                size="base"
                variant="secondary"
                @changed="setActiveState"></TimeTrackerStartStop>
        </div>
        <div
            v-if="!isOnBreak && !isRunningInDifferentOrganization"
            class="flex items-center gap-1 mt-1.5 -mx-1 min-w-0"
            data-testid="sidebar_timer_project_controls">
            <TimeTrackerProjectTaskDropdown
                v-model:project="currentTimeEntry.project_id"
                v-model:task="currentTimeEntry.task_id"
                variant="ghost"
                size="xs"
                align="start"
                trigger-label="Timer project selection"
                class="min-w-0 max-w-[60%] text-xs"
                :projects="projects"
                :tasks="tasks"
                :clients="clients"
                :create-project="createProject"
                :create-client="createClient"
                :can-create-project="canCreateProjects()"
                :currency="getOrganizationCurrencyString()"
                :organization-billable-rate="organization?.billable_rate ?? null"
                :enable-estimated-time="isAllowedToPerformPremiumAction()"
                @changed="updateProject"></TimeTrackerProjectTaskDropdown>
            <TimeTrackerTagDropdown
                v-model="currentTimeEntry.tags"
                show-label
                trigger-label="Timer tag selection"
                test-id="sidebar_tag_dropdown"
                trigger-class="h-7 px-1.5 text-xs flex-1"
                :tags="tags"
                :create-tag="createTag"
                @changed="updateTimeEntry"></TimeTrackerTagDropdown>
        </div>
    </div>
</template>
