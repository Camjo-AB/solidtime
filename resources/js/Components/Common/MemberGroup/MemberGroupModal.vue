<script setup lang="ts">
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import DialogModal from '@/packages/ui/src/DialogModal.vue';
import MultiselectDropdown from '@/packages/ui/src/Input/MultiselectDropdown.vue';
import { Button } from '@/packages/ui/src/Buttons';
import { UserGroupIcon } from '@heroicons/vue/20/solid';
import { computed, ref, watch } from 'vue';
import type { CreateMemberGroupBody, Member, MemberGroup } from '@/packages/api/src';
import { useMembersQuery } from '@/utils/useMembersQuery';

const show = defineModel('show', { default: false });

const props = defineProps<{
    /** The team to edit; creates a new team when omitted. */
    memberGroup?: MemberGroup;
    save: (body: CreateMemberGroupBody) => Promise<unknown>;
}>();

const { members } = useMembersQuery();

const name = ref('');
const memberIds = ref<string[]>([]);
const saving = ref(false);

watch(
    show,
    (isShown) => {
        if (isShown) {
            name.value = props.memberGroup?.name ?? '';
            memberIds.value = [...(props.memberGroup?.member_ids ?? [])];
        }
    },
    { immediate: true }
);

const selectableMembers = computed(() => members.value.filter((member) => !member.is_placeholder));

const membersLabel = computed(() => {
    const names = members.value
        .filter((member) => memberIds.value.includes(member.id))
        .map((member) => member.name);
    if (names.length === 0) return 'Add members';
    if (names.length > 3) return `${names.slice(0, 3).join(', ')} + ${names.length - 3} more`;
    return names.join(', ');
});

function getKeyFromMember(member: Member) {
    return member.id;
}

function getNameForMember(member: Member) {
    return member.name;
}

async function submit() {
    if (name.value.trim() === '' || saving.value) return;
    saving.value = true;
    try {
        const result = await props.save({ name: name.value.trim(), member_ids: memberIds.value });
        if (result !== undefined) {
            show.value = false;
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <DialogModal closeable :show="show" @close="show = false" @submit="submit">
        <template #title>
            {{ memberGroup ? 'Edit team' : 'Create team' }}
        </template>
        <template #content>
            <div class="space-y-4">
                <TextInput
                    v-model="name"
                    type="text"
                    placeholder="Team name"
                    class="block w-full"
                    data-testid="member_group_name"
                    required
                    @keydown.enter="submit" />
                <MultiselectDropdown
                    v-model="memberIds"
                    search-placeholder="Search for a Member..."
                    :items="selectableMembers"
                    :get-key-from-item="getKeyFromMember"
                    :get-name-for-item="getNameForMember">
                    <template #trigger>
                        <Button
                            variant="input"
                            class="w-full min-w-0 justify-start"
                            data-testid="member_group_members">
                            <UserGroupIcon class="h-4 shrink-0 text-icon-default" />
                            <span class="truncate">{{ membersLabel }}</span>
                        </Button>
                    </template>
                </MultiselectDropdown>
                <p class="text-sm text-text-tertiary">
                    Employees in a team can see the tracked time of everyone in their teams in the
                    reporting. They cannot see billable amounts or change the time of others.
                </p>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="show = false">Cancel</SecondaryButton>
            <PrimaryButton
                class="ms-3"
                :class="{ 'opacity-25': saving }"
                :disabled="saving || name.trim() === ''"
                @click="submit">
                {{ memberGroup ? 'Update team' : 'Create team' }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
