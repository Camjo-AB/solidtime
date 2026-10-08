import { computed, type ComputedRef, type Ref } from 'vue';
import type { Dayjs } from 'dayjs';
import type { CalendarSettings } from './calendarSettings';
import type { ExternalCalendarEvent, ExternalEventBox } from './calendarTypes';
import { getLocalizedDayJs } from '../utils/time';

/**
 * Lays out external calendar events (e.g. Google Calendar meetings) per visible day, clamped to the
 * day and to the visible hours, like useActivityBoxes does for activity periods.
 */
export function useExternalEventBoxes(params: {
    externalEvents: () => ExternalCalendarEvent[] | undefined;
    viewDays: ComputedRef<Dayjs[]>;
    calendarSettings: Ref<CalendarSettings>;
    minutesToPixels: (minutes: number) => number;
}) {
    const externalEventBoxes = computed<ExternalEventBox[]>(() => {
        const events = params.externalEvents();
        if (!events || events.length === 0) return [];

        const settings = params.calendarSettings.value;
        const startMin = settings.startHour * 60;
        const endMin = settings.endHour * 60;
        const boxes: ExternalEventBox[] = [];

        for (const day of params.viewDays.value) {
            const dateStr = day.format('YYYY-MM-DD');
            const dayStart = day.startOf('day');
            const dayEnd = day.endOf('day');

            for (const event of events) {
                const eventStart = getLocalizedDayJs(event.start);
                const eventEnd = getLocalizedDayJs(event.end);
                if (!eventEnd.isAfter(dayStart) || eventStart.isAfter(dayEnd)) continue;

                const actualStart = eventStart.isAfter(dayStart) ? eventStart : dayStart;
                const actualEnd = eventEnd.isBefore(dayEnd) ? eventEnd : dayEnd;
                const actualStartMin = actualStart.hour() * 60 + actualStart.minute();
                const actualEndMin =
                    actualEnd === dayEnd ? 24 * 60 : actualEnd.hour() * 60 + actualEnd.minute();

                const clampedStart = Math.max(actualStartMin, startMin);
                const clampedEnd = Math.min(actualEndMin, endMin);
                if (clampedEnd <= clampedStart) continue;

                boxes.push({
                    dateStr,
                    top: params.minutesToPixels(clampedStart - startMin),
                    height: params.minutesToPixels(clampedEnd - clampedStart),
                    event,
                });
            }
        }

        return boxes;
    });

    function externalEventBoxesForDay(dateStr: string): ExternalEventBox[] {
        return externalEventBoxes.value.filter((box) => box.dateStr === dateStr);
    }

    return { externalEventBoxes, externalEventBoxesForDay };
}
