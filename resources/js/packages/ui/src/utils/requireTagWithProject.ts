import { computed, inject, type ComputedRef } from 'vue';
import type { Organization } from '@/packages/api/src';

export const TAG_REQUIRED_WITH_PROJECT_MESSAGE = 'A tag is required when a project is selected.';

/**
 * Whether the organization requires a tag on finished time entries that have a project.
 *
 * Components below the app layout can call this with no argument (the layout
 * provides `organization`); pages that sit above the layout pass their own
 * organization ref. Without an organization the requirement counts as off.
 */
export function useRequireTagWithProject(organization?: {
    value: Organization | undefined | null;
}): ComputedRef<boolean> {
    const org =
        organization ??
        inject<ComputedRef<Organization | undefined> | undefined>('organization', undefined);
    return computed(() => org?.value?.require_tag_with_project ?? false);
}

/**
 * Whether a work entry has a project but no tag while the organization requires one.
 * Break entries never have a project, so they are always fine.
 */
export function isMissingRequiredTag(
    entry: {
        project_id?: string | null;
        tags?: string[] | null;
        type?: string | null;
    },
    required: boolean
): boolean {
    return (
        required && entry.type !== 'break' && !!entry.project_id && (entry.tags?.length ?? 0) === 0
    );
}
