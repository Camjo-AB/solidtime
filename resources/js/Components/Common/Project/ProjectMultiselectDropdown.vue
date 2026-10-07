<script setup lang="ts">
import MultiselectDropdown from '@/packages/ui/src/Input/MultiselectDropdown.vue';
import { useReportingEntities } from '@/utils/useReportingEntities';
import type { Project } from '@/packages/api/src';

const { projects } = useReportingEntities();

function getKeyFromItem(item: Project) {
    return item.id;
}

function getNameForItem(item: Project) {
    return item.name;
}

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <MultiselectDropdown
        search-placeholder="Search for a Project..."
        :items="projects"
        :get-key-from-item="getKeyFromItem"
        :get-name-for-item="getNameForItem"
        no-item-label="No Project"
        @submit="emit('submit')">
        <template #trigger>
            <slot name="trigger"></slot>
        </template>
    </MultiselectDropdown>
</template>
