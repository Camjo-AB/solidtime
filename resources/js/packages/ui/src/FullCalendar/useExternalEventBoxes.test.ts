import { computed, ref } from 'vue';
import { describe, expect, it } from 'vitest';
import { getLocalizedDayJs } from '../utils/time';
import { useExternalEventBoxes } from './useExternalEventBoxes';
import type { ExternalCalendarEvent } from './calendarTypes';

// One pixel per minute keeps the expected positions readable
const day = getLocalizedDayJs('2026-10-06T12:00:00Z').startOf('day');
const nextDay = day.add(1, 'day');

function at(base: typeof day, hour: number, minute = 0): string {
    return base.hour(hour).minute(minute).utc().toISOString();
}

function boxes(events: ExternalCalendarEvent[], startHour = 0, endHour = 24) {
    const { externalEventBoxes, externalEventBoxesForDay } = useExternalEventBoxes({
        externalEvents: () => events,
        viewDays: computed(() => [day, nextDay]),
        calendarSettings: ref({ snapMinutes: 15, startHour, endHour, slotMinutes: 15 }),
        minutesToPixels: (minutes) => minutes,
    });
    return { all: externalEventBoxes.value, forDay: externalEventBoxesForDay };
}

describe('useExternalEventBoxes', () => {
    it('places a meeting on its day at its time', () => {
        const meeting = { id: 'm', title: 'Internmöte', start: at(day, 9), end: at(day, 10, 30) };
        const { forDay } = boxes([meeting]);

        expect(forDay(day.format('YYYY-MM-DD'))).toEqual([
            { dateStr: day.format('YYYY-MM-DD'), top: 9 * 60, height: 90, event: meeting },
        ]);
        expect(forDay(nextDay.format('YYYY-MM-DD'))).toEqual([]);
    });

    it('clamps a meeting to the visible hours', () => {
        const early = { id: 'e', title: 'Frukost', start: at(day, 6), end: at(day, 9) };
        const { all } = boxes([early], 8, 18);

        expect(all).toHaveLength(1);
        expect(all[0]).toMatchObject({ top: 0, height: 60 });
    });

    it('leaves out meetings outside the visible hours', () => {
        const late = { id: 'l', title: 'Middag', start: at(day, 19), end: at(day, 21) };
        expect(boxes([late], 8, 18).all).toEqual([]);
    });

    it('splits a meeting across midnight into one box per day', () => {
        const overnight = { id: 'o', title: 'Release', start: at(day, 23), end: at(nextDay, 1) };
        const { forDay } = boxes([overnight]);

        expect(forDay(day.format('YYYY-MM-DD'))[0]).toMatchObject({ top: 23 * 60 });
        expect(forDay(nextDay.format('YYYY-MM-DD'))[0]).toMatchObject({ top: 0, height: 60 });
    });
});
