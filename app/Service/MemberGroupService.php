<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

class MemberGroupService
{
    public function isInAnyGroup(Member $member): bool
    {
        return DB::table('member_group_member')
            ->where('member_id', $member->getKey())
            ->exists();
    }

    /**
     * IDs of the members whose time entries the member may view through their groups:
     * everyone who shares at least one group with them, and the member themself.
     *
     * @return array<string>
     */
    public function visibleMemberIds(Member $member): array
    {
        /** @var array<string> $memberIds */
        $memberIds = DB::table('member_group_member as own_groups')
            ->join('member_group_member as group_members', 'group_members.member_group_id', '=', 'own_groups.member_group_id')
            ->where('own_groups.member_id', $member->getKey())
            ->distinct()
            ->pluck('group_members.member_id')
            ->all();

        if (! in_array($member->getKey(), $memberIds, true)) {
            $memberIds[] = $member->getKey();
        }

        return $memberIds;
    }

    /**
     * Moves the group memberships of one member to another, e.g. when merging members.
     */
    public function moveMemberships(Member $fromMember, Member $toMember): void
    {
        $groupIds = DB::table('member_group_member')
            ->where('member_id', $fromMember->getKey())
            ->pluck('member_group_id');

        foreach ($groupIds as $groupId) {
            DB::table('member_group_member')->insertOrIgnore([
                'member_group_id' => $groupId,
                'member_id' => $toMember->getKey(),
            ]);
        }

        DB::table('member_group_member')
            ->where('member_id', $fromMember->getKey())
            ->delete();
    }
}
