import type { Tag } from '@/packages/api/src';

/** Names of the selected tags, in the order of the organization's tag list. */
export function selectedTagNames(tagIds: string[], tags: Tag[]): string[] {
    return tags.filter((tag) => tagIds.includes(tag.id)).map((tag) => tag.name);
}

/** Short label for a tag button: "A", "A, B" or "A + 2 more". */
export function tagLabel(tagIds: string[], tags: Tag[]): string {
    const names = selectedTagNames(tagIds, tags);
    if (names.length >= 3) {
        return `${names[0]} + ${names.length - 1} more`;
    }
    return names.join(', ');
}
