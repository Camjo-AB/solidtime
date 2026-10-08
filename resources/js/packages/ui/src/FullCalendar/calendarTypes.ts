import type { TimeEntry, Project, Client, Task } from '@/packages/api/src';
import type { Dayjs } from 'dayjs';
import type { ActivityPeriod } from './activityTypes';

export const SLOT_HEIGHT = 25;
export const DRAG_THRESHOLD = 5;
export const TIME_AXIS_WIDTH = 48;

export interface CalendarEvent {
    id: string;
    timeEntry: TimeEntry;
    project?: Project;
    client?: Client;
    task?: Task;
    /** Tag label for the event, e.g. "Internmöte" or "Internmöte + 2 more"; empty without tags. */
    tagLabel: string;
    isRunning: boolean;
    isBreak: boolean;
    isMisplacedBreak: boolean;
    durationMinutes: number;
    title: string;
    backgroundColor: string;
    borderColor: string;
    dayStart: Dayjs;
    dayEnd: Dayjs;
}

/** A read-only event from an external calendar (e.g. Google Calendar), shown next to time entries. */
export interface ExternalCalendarEvent {
    id: string;
    title: string;
    /** ISO 8601, UTC */
    start: string;
    /** ISO 8601, UTC */
    end: string;
    htmlLink?: string | null;
}

export interface ExternalEventBox {
    dateStr: string;
    top: number;
    height: number;
    event: ExternalCalendarEvent;
}

export interface DayEvent {
    event: CalendarEvent;
    top: number;
    height: number;
    left: string;
    width: string;
    isClippedStart: boolean;
    isClippedEnd: boolean;
}

export interface ActivityBox {
    dateStr: string;
    top: number;
    height: number;
    isIdle: boolean;
    period: ActivityPeriod;
}
