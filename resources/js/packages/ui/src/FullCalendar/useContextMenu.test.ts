import { computed, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { TimeEntry } from '@/packages/api/src';
import type { CalendarEvent } from './calendarTypes';
import { useContextMenu } from './useContextMenu';
import { copiedTimeEntry } from './timeEntryClipboard';

function breakEntry(): TimeEntry {
    return {
        id: 'break-1',
        start: '2026-07-14T10:00:00Z',
        end: '2026-07-14T11:00:00Z',
        duration: 3600,
        description: 'Lunch',
        project_id: null,
        task_id: null,
        organization_id: 'organization-1',
        user_id: 'user-1',
        tags: [],
        billable: false,
        type: 'break',
    } as TimeEntry;
}

function workEntry(overrides: Partial<TimeEntry>): TimeEntry {
    return {
        id: 'work-1',
        start: '2026-07-14T08:00:00Z',
        end: '2026-07-14T09:00:00Z',
        duration: 3600,
        description: null,
        project_id: null,
        task_id: null,
        organization_id: 'organization-1',
        user_id: 'user-1',
        tags: [],
        billable: false,
        type: 'work',
        ...overrides,
    } as TimeEntry;
}

describe('useContextMenu', () => {
    const createTimeEntry = vi.fn().mockResolvedValue(undefined);
    const updateTimeEntry = vi.fn().mockResolvedValue(undefined);
    const onCreateEvent = vi.fn();

    beforeEach(() => {
        vi.clearAllMocks();
        copiedTimeEntry.value = null;
    });

    function contextMenu(entries: TimeEntry[]) {
        const calendarEvents = computed(() =>
            entries.map((entry) => ({ id: entry.id, timeEntry: entry }) as CalendarEvent)
        );
        return useContextMenu({
            calendarSettings: ref({
                snapMinutes: 15,
                startHour: 0,
                endHour: 24,
                slotMinutes: 15,
            }),
            calendarEvents,
            // The grid starts at midnight, so one pixel is one minute in the clicked day
            pixelsToMinutesFromMidnight: (px) => px,
            getDayFromClientX: () => '2026-07-14',
            clientYToGridPixels: (clientY) => clientY,
            createTimeEntry,
            updateTimeEntry,
            deleteTimeEntry: vi.fn().mockResolvedValue(undefined),
            onEditEvent: vi.fn(),
            onCreateEvent,
            onCreateBreak: vi.fn(),
            emitRefresh: vi.fn(),
        });
    }

    function rightClickEntry(menu: ReturnType<typeof contextMenu>, entryId: string) {
        menu.handleCalendarContextMenu({
            target: {
                closest: () => ({
                    getAttribute: () => entryId,
                }),
            },
        } as unknown as MouseEvent);
    }

    function rightClickEmpty(menu: ReturnType<typeof contextMenu>, minutesFromMidnight: number) {
        menu.handleCalendarContextMenu({
            target: { closest: () => null },
            clientX: 0,
            clientY: minutesFromMidnight,
        } as unknown as MouseEvent);
    }

    describe('break actions', () => {
        it('preserves the type when duplicating a break', async () => {
            const menu = contextMenu([breakEntry()]);
            rightClickEntry(menu, 'break-1');
            await menu.handleContextDuplicate();

            expect(createTimeEntry).toHaveBeenCalledWith(
                expect.objectContaining({
                    type: 'break',
                })
            );
        });

        it('preserves the type when creating the second half of a split break', async () => {
            const menu = contextMenu([breakEntry()]);
            rightClickEntry(menu, 'break-1');
            await menu.handleContextSplit();

            expect(updateTimeEntry).toHaveBeenCalledWith(
                expect.objectContaining({
                    type: 'break',
                })
            );
            expect(createTimeEntry).toHaveBeenCalledWith(
                expect.objectContaining({
                    type: 'break',
                })
            );
        });
    });

    describe('copy and paste', () => {
        const source = workEntry({
            id: 'source',
            description: 'Länkplattform',
            project_id: 'project-1',
            task_id: 'task-1',
            tags: ['tag-1'],
            billable: true,
        });
        const target = workEntry({
            id: 'target',
            start: '2026-07-14T10:00:00Z',
            end: '2026-07-14T10:30:00Z',
            duration: 1800,
        });

        it('pastes description, project, task, tags and billable onto another entry', async () => {
            const menu = contextMenu([source, target]);
            rightClickEntry(menu, 'source');
            menu.handleContextCopy();
            rightClickEntry(menu, 'target');
            await menu.handleContextPaste();

            expect(updateTimeEntry).toHaveBeenCalledWith({
                ...target,
                description: 'Länkplattform',
                project_id: 'project-1',
                task_id: 'task-1',
                tags: ['tag-1'],
                billable: true,
            });
        });

        it('opens the create modal prefilled with the gap between two entries', async () => {
            const menu = contextMenu([source, target]);
            rightClickEntry(menu, 'source');
            menu.handleContextCopy();
            // 09:30 UTC, between source (ends 09:00) and target (starts 10:00)
            rightClickEmpty(menu, 9 * 60 + 30);
            await menu.handleContextPaste();

            expect(onCreateEvent).toHaveBeenCalledTimes(1);
            const [start, end, prefill] = onCreateEvent.mock.calls[0] ?? [];
            expect(start?.toISOString()).toBe('2026-07-14T09:00:00.000Z');
            expect(end?.toISOString()).toBe('2026-07-14T10:00:00.000Z');
            expect(prefill).toEqual({
                description: 'Länkplattform',
                project_id: 'project-1',
                task_id: 'task-1',
                tags: ['tag-1'],
                billable: true,
            });
        });

        it('does nothing when nothing was copied', async () => {
            const menu = contextMenu([source, target]);
            rightClickEntry(menu, 'target');
            await menu.handleContextPaste();
            rightClickEmpty(menu, 9 * 60 + 30);
            await menu.handleContextPaste();

            expect(updateTimeEntry).not.toHaveBeenCalled();
            expect(onCreateEvent).not.toHaveBeenCalled();
        });

        it('does not copy a break', () => {
            const menu = contextMenu([breakEntry()]);
            rightClickEntry(menu, 'break-1');
            menu.handleContextCopy();

            expect(copiedTimeEntry.value).toBeNull();
        });

        it('does not paste onto a break', async () => {
            const menu = contextMenu([source, breakEntry()]);
            rightClickEntry(menu, 'source');
            menu.handleContextCopy();
            rightClickEntry(menu, 'break-1');
            await menu.handleContextPaste();

            expect(updateTimeEntry).not.toHaveBeenCalled();
        });
    });
});
