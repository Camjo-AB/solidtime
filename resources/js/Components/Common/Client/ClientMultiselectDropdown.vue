<script setup lang="ts">
import MultiselectDropdown from '@/packages/ui/src/Input/MultiselectDropdown.vue';
import type { Client } from '@/packages/api/src';
import { useReportingEntities } from '@/utils/useReportingEntities';

const { clients } = useReportingEntities();

function getKeyFromItem(item: Client) {
    return item.id;
}

function getNameForItem(item: Client) {
    return item.name;
}

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <MultiselectDropdown
        search-placeholder="Search for a Client..."
        :items="clients"
        :get-key-from-item="getKeyFromItem"
        :get-name-for-item="getNameForItem"
        no-item-label="No Client"
        @submit="emit('submit')">
        <template #trigger>
            <slot name="trigger"></slot>
        </template>
    </MultiselectDropdown>
</template>
