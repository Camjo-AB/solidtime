import { useQuery } from '@tanstack/vue-query';
import { computed, type Ref } from 'vue';
import type { Dayjs } from 'dayjs';
import { api } from '@/packages/api/src';
import type { ExternalCalendarEvent } from '@/packages/ui/src';

/** Whether the server has the Google Calendar connection configured and the user has connected. */
export function useGoogleCalendarStatus() {
    const query = useQuery({
        queryKey: ['google-calendar-status'],
        queryFn: async () => (await api.getMyGoogleCalendar({})).data,
        staleTime: 1000 * 60 * 5,
    });

    const enabled = computed(() => query.data.value?.enabled ?? false);
    const connected = computed(() => query.data.value?.connected ?? false);
    const email = computed(() => query.data.value?.email ?? null);

    return { ...query, enabled, connected, email };
}

/** Meetings of the connected Google Calendar in the visible range, for the calendar view. */
export function useGoogleCalendarEvents(
    start: Ref<Dayjs | undefined>,
    end: Ref<Dayjs | undefined>
) {
    const { connected } = useGoogleCalendarStatus();

    const query = useQuery({
        queryKey: computed(() => [
            'google-calendar-events',
            start.value?.toISOString(),
            end.value?.toISOString(),
        ]),
        queryFn: async () => {
            const response = await api.getMyGoogleCalendarEvents({
                queries: {
                    start: start.value!.utc().format('YYYY-MM-DDTHH:mm:ss[Z]'),
                    end: end.value!.utc().format('YYYY-MM-DDTHH:mm:ss[Z]'),
                },
            });
            return response.data;
        },
        enabled: () => connected.value && !!start.value && !!end.value,
        staleTime: 1000 * 60,
        // An expired connection answers with an error; do not hammer Google
        retry: false,
    });

    const events = computed<ExternalCalendarEvent[]>(() =>
        (query.data.value ?? []).map((event) => ({
            id: event.id,
            title: event.title,
            start: event.start,
            end: event.end,
            htmlLink: event.html_link,
        }))
    );

    return { ...query, events };
}
