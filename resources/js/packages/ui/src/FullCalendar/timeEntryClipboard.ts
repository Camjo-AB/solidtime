import { ref } from 'vue';
import type { TimeEntry } from '@/packages/api/src';

export type CopiedTimeEntryFields = Pick<
    TimeEntry,
    'description' | 'project_id' | 'task_id' | 'tags' | 'billable'
>;

// Module level so a copied entry survives week navigation and leaving the calendar page
export const copiedTimeEntry = ref<CopiedTimeEntryFields | null>(null);
