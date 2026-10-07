<script setup lang="ts">
import TagDropdown from '@/packages/ui/src/Tag/TagDropdown.vue';
import { computed } from 'vue';
import TagBadge from '@/packages/ui/src/Tag/TagBadge.vue';
import type { Tag } from '@/packages/api/src';
import { selectedTagNames, tagLabel } from '@/packages/ui/src/utils/tags';

const props = withDefaults(
    defineProps<{
        tags: Tag[];
        createTag: (name: string) => Promise<Tag | undefined>;
        compact?: boolean;
    }>(),
    {
        compact: false,
    }
);

const emit = defineEmits<{
    changed: [model: string[]];
}>();

const model = defineModel<string[]>({
    default: () => [],
});

const timeEntryTags = computed<Tag[]>(() => {
    return props.tags.filter((tag) => model.value.includes(tag.id));
});

const allTagNames = computed(() => selectedTagNames(model.value, props.tags).join(', '));

const displayName = computed(() => tagLabel(model.value, props.tags));
</script>
<template>
    <TagDropdown
        v-model="model"
        :tags="tags"
        align="end"
        :show-no-tag-option="false"
        :create-tag
        @changed="emit('changed', model)">
        <template #trigger>
            <button
                data-testid="time_entry_tag_dropdown"
                :title="allTagNames || undefined"
                :class="[
                    'group/dropdown focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:opacity-100 transition focus:bg-card-background-separator hover:bg-card-background-separator rounded-full flex items-center justify-center',
                    compact ? '' : 'opacity-50 group-hover:opacity-100',
                ]">
                <TagBadge
                    :border="false"
                    size="large"
                    :show-icon="!(compact && timeEntryTags.length > 0)"
                    :class="
                        'border-0 sm:px-1.5 text-icon-default group-focus-within/dropdown:text-text-primary whitespace-nowrap [&>span]:truncate' +
                        (compact ? ' max-w-48' : '')
                    "
                    :name="displayName"></TagBadge>
            </button>
        </template>
    </TagDropdown>
</template>

<style scoped></style>
