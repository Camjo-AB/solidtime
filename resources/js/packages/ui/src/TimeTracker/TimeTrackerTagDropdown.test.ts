import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import TimeTrackerTagDropdown from './TimeTrackerTagDropdown.vue';
import type { Tag } from '@/packages/api/src';

const tags = [
    { id: 'a', name: 'Internmöte' },
    { id: 'b', name: 'Kund' },
    { id: 'c', name: 'Resa' },
] as Tag[];

function mountDropdown(modelValue: string[], showLabel = true) {
    return mount(TimeTrackerTagDropdown, {
        props: { modelValue, tags, createTag: vi.fn(), showLabel },
        global: {
            stubs: {
                // Render only the trigger; the dropdown itself is covered by its own tests.
                TagDropdown: { template: '<div><slot name="trigger" /></div>' },
            },
        },
    });
}

describe('TimeTrackerTagDropdown', () => {
    it('shows "Tags" when nothing is selected', () => {
        expect(mountDropdown([]).get('[data-testid="tag_dropdown"]').text()).toBe('Tags');
    });

    it('shows the name of the selected tag', () => {
        expect(mountDropdown(['a']).get('[data-testid="tag_dropdown"]').text()).toBe('Internmöte');
    });

    it('shortens three or more tags and lists all of them in the tooltip', () => {
        const trigger = mountDropdown(['a', 'b', 'c']).get('[data-testid="tag_dropdown"]');
        expect(trigger.text()).toBe('Internmöte + 2 more');
        expect(trigger.attributes('title')).toBe('Internmöte, Kund, Resa');
    });

    it('only shows the icon without showLabel', () => {
        expect(mountDropdown(['a'], false).get('[data-testid="tag_dropdown"]').text()).toBe('');
    });
});
