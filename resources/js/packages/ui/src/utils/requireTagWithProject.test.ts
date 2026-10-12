import { describe, expect, it } from 'vitest';
import { isMissingRequiredTag } from './requireTagWithProject';

describe('isMissingRequiredTag', () => {
    it('flags a work entry with a project and no tags', () => {
        expect(isMissingRequiredTag({ project_id: 'p', tags: [], type: 'work' }, true)).toBe(true);
    });

    it('treats missing tags like no tags', () => {
        expect(isMissingRequiredTag({ project_id: 'p', tags: null }, true)).toBe(true);
    });

    it('accepts a project with a tag', () => {
        expect(isMissingRequiredTag({ project_id: 'p', tags: ['t'] }, true)).toBe(false);
    });

    it('accepts an entry without a project', () => {
        expect(isMissingRequiredTag({ project_id: null, tags: [] }, true)).toBe(false);
    });

    it('ignores breaks', () => {
        expect(isMissingRequiredTag({ project_id: 'p', tags: [], type: 'break' }, true)).toBe(
            false
        );
    });

    it('does nothing when the organization does not require tags', () => {
        expect(isMissingRequiredTag({ project_id: 'p', tags: [] }, false)).toBe(false);
    });
});
