<script setup lang="ts">
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import { PlusIcon } from '@heroicons/vue/16/solid';
import { PencilIcon, TrashIcon } from '@heroicons/vue/20/solid';
import { UserGroupIcon } from '@heroicons/vue/24/solid';
import { computed, ref } from 'vue';
import type { CreateMemberGroupBody, MemberGroup } from '@/packages/api/src';
import MemberGroupModal from '@/Components/Common/MemberGroup/MemberGroupModal.vue';
import { useMemberGroups } from '@/utils/useMemberGroups';
import { useMembersQuery } from '@/utils/useMembersQuery';
import {
    canCreateMemberGroups,
    canDeleteMemberGroups,
    canUpdateMemberGroups,
} from '@/utils/permissions';

const { memberGroups, createMemberGroup, updateMemberGroup, deleteMemberGroup } = useMemberGroups();
const { members } = useMembersQuery();

const showModal = ref(false);
const editedGroup = ref<MemberGroup | undefined>(undefined);
const groupPendingDelete = ref<string | null>(null);

const sortedGroups = computed(() =>
    [...memberGroups.value].sort((a, b) => a.name.localeCompare(b.name))
);

function memberNames(memberGroup: MemberGroup): string {
    const names = members.value
        .filter((member) => memberGroup.member_ids.includes(member.id))
        .map((member) => member.name)
        .sort((a, b) => a.localeCompare(b));
    return names.length > 0 ? names.join(', ') : 'No members yet';
}

function openCreate() {
    editedGroup.value = undefined;
    showModal.value = true;
}

function openEdit(memberGroup: MemberGroup) {
    editedGroup.value = memberGroup;
    showModal.value = true;
}

function save(body: CreateMemberGroupBody) {
    if (editedGroup.value) {
        return updateMemberGroup({ id: editedGroup.value.id, body });
    }
    return createMemberGroup(body);
}

async function onDelete(memberGroup: MemberGroup) {
    // Ask once inline instead of a browser dialog; deleting a team keeps all time entries.
    if (groupPendingDelete.value !== memberGroup.id) {
        groupPendingDelete.value = memberGroup.id;
        return;
    }
    groupPendingDelete.value = null;
    await deleteMemberGroup(memberGroup.id);
}
</script>

<template>
    <MemberGroupModal v-model:show="showModal" :member-group="editedGroup" :save />
    <div class="px-3 sm:px-4 lg:px-6 py-4 space-y-3" data-testid="member_groups">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-text-secondary">
                Employees see the time of everyone in their teams in the reporting.
            </p>
            <SecondaryButton
                v-if="canCreateMemberGroups()"
                :icon="PlusIcon"
                data-testid="create_member_group"
                @click="openCreate">
                Create team
            </SecondaryButton>
        </div>
        <div
            v-if="sortedGroups.length === 0"
            class="flex flex-col items-center py-12 text-center text-text-secondary">
            <UserGroupIcon class="w-8 text-icon-default mb-3" />
            <p class="font-medium text-text-primary">No teams yet</p>
            <p class="text-sm">Create a team to let its members see each other's time.</p>
        </div>
        <ul
            v-else
            class="divide-y divide-default-background-separator border border-card-border rounded-lg bg-card-background">
            <li
                v-for="memberGroup in sortedGroups"
                :key="memberGroup.id"
                class="flex items-center justify-between gap-4 px-4 py-3"
                data-testid="member_group_row">
                <div class="min-w-0">
                    <div class="font-medium text-text-primary">{{ memberGroup.name }}</div>
                    <div class="text-sm text-text-secondary truncate">
                        {{ memberGroup.member_ids.length }}
                        {{ memberGroup.member_ids.length === 1 ? 'member' : 'members' }} ·
                        {{ memberNames(memberGroup) }}
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <SecondaryButton
                        v-if="canUpdateMemberGroups()"
                        :icon="PencilIcon"
                        size="small"
                        @click="openEdit(memberGroup)">
                        Edit
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="canDeleteMemberGroups()"
                        :icon="TrashIcon"
                        size="small"
                        @click="onDelete(memberGroup)">
                        {{
                            groupPendingDelete === memberGroup.id
                                ? 'Click again to delete'
                                : 'Delete'
                        }}
                    </SecondaryButton>
                </div>
            </li>
        </ul>
    </div>
</template>
