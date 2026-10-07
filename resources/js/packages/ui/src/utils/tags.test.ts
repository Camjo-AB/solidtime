import { describe, expect, it } from 'vitest';
import type { Tag } from '@/packages/api/src';
import { selectedTagNames, tagLabel } from './tags';

const tags = [
    { id: 'a', name: 'Internmöte' },
    { id: 'b', name: 'Kund' },
    { id: 'c', name: 'Resa' },
] as Tag[];

describe('tagLabel', () => {
    it('is empty without tags', () => {
        expect(tagLabel([], tags)).toBe('');
    });

    it('shows the name of a single tag', () => {
        expect(tagLabel(['a'], tags)).toBe('Internmöte');
    });

    it('joins two tag names', () => {
        expect(tagLabel(['b', 'a'], tags)).toBe('Internmöte, Kund');
    });

    it('shortens three or more tags', () => {
        expect(tagLabel(['a', 'b', 'c'], tags)).toBe('Internmöte + 2 more');
    });

    it('ignores ids that are not in the tag list', () => {
        expect(tagLabel(['a', 'missing'], tags)).toBe('Internmöte');
        expect(selectedTagNames(['missing'], tags)).toEqual([]);
    });
});
