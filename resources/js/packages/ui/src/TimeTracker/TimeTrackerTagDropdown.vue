<script setup lang="ts">
import TagDropdown from '@/packages/ui/src/Tag/TagDropdown.vue';
import { twMerge } from 'tailwind-merge';
import { TagIcon } from '@heroicons/vue/20/solid';
import { computed } from 'vue';
import type { Tag } from '@/packages/api/src';
import { selectedTagNames, tagLabel } from '@/packages/ui/src/utils/tags';

const emit = defineEmits<{
    changed: [];
}>();

const model = defineModel<string[]>({
    default: () => [],
});
const iconColorClasses = computed(() => {
    if (model.value.length > 0) {
        return 'text-input-select-active focus:text-input-select-active-hover hover:text-input-select-active-hover';
    } else {
        return 'text-icon-default hover:text-icon-active focus:text-icon-active';
    }
});
const props = defineProps<{
    tags: Tag[];
    createTag: (name: string) => Promise<Tag | undefined>;
    /** Show the selected tag names next to the icon instead of only the icon. */
    showLabel?: boolean;
    /** Extra classes for the labelled trigger, e.g. to fit a compact host. */
    triggerClass?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const label = computed(() => tagLabel(model.value, props.tags));
const allTagNames = computed(() => selectedTagNames(model.value, props.tags).join(', '));
</script>

<template>
    <TagDropdown
        v-model="model"
        v-model:open="open"
        :create-tag
        :tags="tags"
        :show-no-tag-option="false"
        @changed="emit('changed')">
        <template #trigger>
            <button
                v-if="showLabel"
                data-testid="tag_dropdown"
                :title="allTagNames || undefined"
                :class="
                    twMerge(
                        'flex items-center gap-1.5 min-w-0 max-w-full h-8 px-2 rounded-md text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring hover:bg-card-background-separator',
                        model.length > 0 ? 'text-text-primary' : 'text-text-tertiary',
                        triggerClass
                    )
                ">
                <TagIcon
                    class="w-4 h-4 shrink-0"
                    :class="model.length > 0 ? 'text-input-select-active' : 'text-icon-default'" />
                <span class="truncate">{{ label || 'Tags' }}</span>
            </button>
            <button
                v-else
                data-testid="tag_dropdown"
                :class="
                    twMerge(
                        iconColorClasses,
                        'relative flex-shrink-0 ring-0 focus:outline-none focus:ring-2 focus:ring-ring transition focus-visible:bg-card-background-separator hover:bg-card-background-separator rounded-full w-10 h-10 flex items-center justify-center'
                    )
                ">
                <TagIcon class="w-5 h-5 lg:h-6 lg:w-6"></TagIcon>
            </button>
        </template>
    </TagDropdown>
</template>

<style scoped></style>
