<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTimeEntriesCalendarQuery } from '@/utils/useTimeEntriesCalendarQuery';
import { useTimeEntriesMutations } from '@/utils/useTimeEntriesMutations';
import { computed, ref, onMounted } from 'vue';
import type { Dayjs } from 'dayjs';
import { useQueryClient } from '@tanstack/vue-query';
import {
    type Client,
    type CreateClientBody,
    type CreateProjectBody,
    type Project,
} from '@/packages/api/src';
import { TimeEntryCalendar } from '@/packages/ui/src';
import type { ActivityPeriod } from '@/packages/ui/src/FullCalendar/activityTypes';
import { isAllowedToPerformPremiumAction } from '@/utils/billing';
import { useTagsStore } from '@/utils/useTags';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useClientsQuery } from '@/utils/useClientsQuery';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useProjectsStore } from '@/utils/useProjects';
import { useClientsStore } from '@/utils/useClients';
import { getOrganizationCurrencyString } from '@/utils/money';
import { canCreateProjects } from '@/utils/permissions';
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import { useOrganizationQuery } from '@/utils/useOrganizationQuery';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useGoogleCalendarEvents, useGoogleCalendarStatus } from '@/utils/useGoogleCalendar';
import { useLocalStorage } from '@vueuse/core';
import { CalendarIcon, XMarkIcon } from '@heroicons/vue/20/solid';

const { organization } = useOrganizationQuery(getCurrentOrganizationId()!);
const calendarStart = ref<Dayjs | undefined>(undefined);
const calendarEnd = ref<Dayjs | undefined>(undefined);

// Optional deep link (e.g. "Fix in calendar") that opens the calendar on a specific day
const initialDate = new URLSearchParams(window.location.search).get('date');

// Test-injectable activity periods (for E2E testing).
// These hooks are no-ops in production — they only take effect when test code
// explicitly sets window globals, so they are safe to ship.
const testActivityPeriods = ref<ActivityPeriod[]>([]);

onMounted(() => {
    (window as unknown as Record<string, unknown>).__TEST_SET_ACTIVITY_PERIODS__ = (
        data: ActivityPeriod[]
    ) => {
        testActivityPeriods.value = data;
    };

    const windowData = (window as unknown as Record<string, unknown>).__TEST_ACTIVITY_PERIODS__;
    if (Array.isArray(windowData)) {
        setTimeout(() => {
            testActivityPeriods.value = windowData;
        }, 2000);
    }
});

const { data: timeEntryResponse, isLoading: timeEntriesLoading } = useTimeEntriesCalendarQuery(
    calendarStart,
    calendarEnd
);

const currentTimeEntries = computed(() => {
    return timeEntryResponse?.value?.data || [];
});

// Google Calendar meetings, shown read-only next to the time entries
const googleCalendar = useGoogleCalendarStatus();
const { events: googleCalendarEvents, error: googleCalendarError } = useGoogleCalendarEvents(
    calendarStart,
    calendarEnd
);
const googleCalendarHintDismissed = useLocalStorage(
    'solidtime/google-calendar-hint-dismissed',
    false
);
const googleCalendarNeedsReconnect = computed(() => googleCalendarError.value !== null);
const showGoogleCalendarHint = computed(
    () =>
        googleCalendar.enabled.value &&
        (googleCalendarNeedsReconnect.value ||
            (!googleCalendar.connected.value && !googleCalendarHintDismissed.value))
);

const {
    createTimeEntry: createTimeEntryMutation,
    updateTimeEntry: updateTimeEntryMutation,
    deleteTimeEntry: deleteTimeEntryMutation,
} = useTimeEntriesMutations();

// Wrap mutations to match expected Promise<void> return type
async function createTimeEntry(
    entry: Omit<import('@/packages/api/src').TimeEntry, 'id' | 'organization_id' | 'user_id'>
): Promise<void> {
    await createTimeEntryMutation(entry);
}

async function updateTimeEntry(entry: import('@/packages/api/src').TimeEntry): Promise<void> {
    await updateTimeEntryMutation(entry);
}

async function deleteTimeEntry(timeEntryId: string): Promise<void> {
    await deleteTimeEntryMutation(timeEntryId);
}

async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}

async function createProject(project: CreateProjectBody): Promise<Project | undefined> {
    return await useProjectsStore().createProject(project);
}

async function createClient(body: CreateClientBody): Promise<Client | undefined> {
    return await useClientsStore().createClient(body);
}

const { projects } = useProjectsQuery();
const { tasks } = useTasksQuery();
const { clients } = useClientsQuery();
const { tags } = useTagsQuery();

const queryClient = useQueryClient();

function onDatesChange({ start, end }: { start: Dayjs; end: Dayjs }) {
    calendarStart.value = start;
    calendarEnd.value = end;
}

function onRefresh() {
    queryClient.invalidateQueries({
        queryKey: ['timeEntries'],
    });
    useCurrentTimeEntryStore().fetchCurrentTimeEntry();
}
</script>

<template>
    <AppLayout
        title="Calendar"
        data-testid="calendar_view"
        main-class="p-0 min-h-0 overflow-hidden">
        <div class="flex flex-col h-full min-h-0">
            <div
                v-if="showGoogleCalendarHint"
                class="shrink-0 flex items-center gap-2 px-4 py-1.5 text-sm border-b border-default-background-separator text-text-secondary"
                data-testid="google_calendar_hint">
                <CalendarIcon class="w-4 h-4 shrink-0 text-icon-default" />
                <span v-if="googleCalendarNeedsReconnect">
                    Your Google Calendar could not be loaded.
                </span>
                <span v-else>See your meetings here and register them with one click.</span>
                <a
                    href="/google-calendar/connect"
                    class="font-medium text-text-primary underline underline-offset-2 hover:text-primary">
                    {{
                        googleCalendarNeedsReconnect
                            ? 'Reconnect Google Calendar'
                            : 'Connect Google Calendar'
                    }}
                </a>
                <button
                    v-if="!googleCalendarNeedsReconnect"
                    type="button"
                    class="ml-auto p-1 rounded text-icon-default hover:text-text-primary"
                    aria-label="Hide"
                    @click="googleCalendarHintDismissed = true">
                    <XMarkIcon class="w-4 h-4" />
                </button>
            </div>
            <div class="flex-1 min-h-0">
                <TimeEntryCalendar
                    :time-entries="currentTimeEntries"
                    :projects="projects"
                    :tasks="tasks"
                    :clients="clients"
                    :tags="tags"
                    :loading="timeEntriesLoading"
                    :enable-estimated-time="isAllowedToPerformPremiumAction()"
                    :currency="getOrganizationCurrencyString()"
                    :can-create-project="canCreateProjects()"
                    :initial-date="initialDate"
                    :organization-billable-rate="organization?.billable_rate ?? null"
                    :create-time-entry="createTimeEntry"
                    :update-time-entry="updateTimeEntry"
                    :delete-time-entry="deleteTimeEntry"
                    :create-client="createClient"
                    :create-project="createProject"
                    :create-tag="createTag"
                    :activity-periods="testActivityPeriods"
                    :external-events="googleCalendarEvents"
                    @dates-change="onDatesChange"
                    @refresh="onRefresh" />
            </div>
        </div>
    </AppLayout>
</template>
