<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Api\V1\MemberGroupController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Models\User;
use App\Service\MemberGroupService;
use App\Service\MemberService;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(MemberGroupController::class)]
#[UsesClass(TimeEntryController::class)]
#[UsesClass(MemberGroupService::class)]
class MemberGroupEndpointTest extends ApiEndpointTestAbstract
{
    private function createMember(Organization $organization, Role $role = Role::Employee, string $name = 'Member'): Member
    {
        $user = User::factory()->create(['name' => $name]);

        return Member::factory()->forUser($user)->forOrganization($organization)->role($role)->create();
    }

    /**
     * @param  array<Member>  $members
     */
    private function createGroup(Organization $organization, array $members, string $name = 'Team'): MemberGroup
    {
        $memberGroup = MemberGroup::factory()->forOrganization($organization)->create(['name' => $name]);
        $memberGroup->members()->sync(array_map(fn (Member $member): string => $member->getKey(), $members));

        return $memberGroup;
    }

    // Managing groups

    public function test_index_fails_for_employee(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.member-groups.index', [$data->organization->getKey()]));

        $response->assertForbidden();
    }

    public function test_index_returns_groups_of_organization_with_member_ids_for_admin(): void
    {
        $data = $this->createUserWithRole(Role::Admin);
        $employee = $this->createMember($data->organization);
        $memberGroup = $this->createGroup($data->organization, [$employee]);
        $otherOrganization = Organization::factory()->create();
        MemberGroup::factory()->forOrganization($otherOrganization)->create();
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.member-groups.index', [$data->organization->getKey()]));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $memberGroup->getKey());
        $response->assertJsonPath('data.0.member_ids', [$employee->getKey()]);
    }

    public function test_store_creates_group_with_members(): void
    {
        $data = $this->createUserWithRole(Role::Admin);
        $employee1 = $this->createMember($data->organization);
        $employee2 = $this->createMember($data->organization);
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.member-groups.store', [$data->organization->getKey()]), [
            'name' => 'Konsulter',
            'member_ids' => [$employee1->getKey(), $employee2->getKey()],
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.name', 'Konsulter');
        /** @var MemberGroup $memberGroup */
        $memberGroup = MemberGroup::query()->findOrFail($response->json('data.id'));
        $this->assertSame($data->organization->getKey(), $memberGroup->organization_id);
        $this->assertEqualsCanonicalizing(
            [$employee1->getKey(), $employee2->getKey()],
            $memberGroup->members()->pluck('members.id')->all()
        );
    }

    public function test_store_fails_for_employee(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.member-groups.store', [$data->organization->getKey()]), [
            'name' => 'Team',
            'member_ids' => [$data->member->getKey()],
        ]);

        $response->assertForbidden();
        $this->assertSame(0, MemberGroup::query()->count());
    }

    public function test_store_fails_for_member_of_other_organization(): void
    {
        $data = $this->createUserWithRole(Role::Admin);
        $otherOrganization = Organization::factory()->create();
        $foreignMember = $this->createMember($otherOrganization);
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.member-groups.store', [$data->organization->getKey()]), [
            'name' => 'Team',
            'member_ids' => [$foreignMember->getKey()],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['member_ids.0']);
    }

    public function test_update_replaces_name_and_members(): void
    {
        $data = $this->createUserWithRole(Role::Owner);
        $employee1 = $this->createMember($data->organization);
        $employee2 = $this->createMember($data->organization);
        $memberGroup = $this->createGroup($data->organization, [$employee1]);
        Passport::actingAs($data->user);

        $response = $this->putJson(route('api.v1.member-groups.update', [$data->organization->getKey(), $memberGroup->getKey()]), [
            'name' => 'Nytt namn',
            'member_ids' => [$employee2->getKey()],
        ]);

        $response->assertSuccessful();
        $memberGroup->refresh();
        $this->assertSame('Nytt namn', $memberGroup->name);
        $this->assertSame([$employee2->getKey()], $memberGroup->members()->pluck('members.id')->all());
    }

    public function test_update_fails_for_group_of_other_organization(): void
    {
        $data = $this->createUserWithRole(Role::Admin);
        $otherOrganization = Organization::factory()->create();
        $foreignGroup = MemberGroup::factory()->forOrganization($otherOrganization)->create(['name' => 'Foreign']);
        Passport::actingAs($data->user);

        $response = $this->putJson(route('api.v1.member-groups.update', [$data->organization->getKey(), $foreignGroup->getKey()]), [
            'name' => 'Hijacked',
            'member_ids' => [],
        ]);

        $response->assertForbidden();
        $this->assertSame('Foreign', $foreignGroup->refresh()->name);
    }

    public function test_destroy_deletes_group_and_memberships(): void
    {
        $data = $this->createUserWithRole(Role::Admin);
        $employee = $this->createMember($data->organization);
        $memberGroup = $this->createGroup($data->organization, [$employee]);
        Passport::actingAs($data->user);

        $response = $this->deleteJson(route('api.v1.member-groups.destroy', [$data->organization->getKey(), $memberGroup->getKey()]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('member_groups', ['id' => $memberGroup->getKey()]);
        $this->assertDatabaseMissing('member_group_member', ['member_group_id' => $memberGroup->getKey()]);
        $this->assertDatabaseHas('members', ['id' => $employee->getKey()]);
    }

    // Team members

    public function test_team_members_returns_only_members_sharing_a_group_without_private_fields(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $teammate = $this->createMember($data->organization, Role::Employee, 'Anna');
        $outsider = $this->createMember($data->organization, Role::Employee, 'Bertil');
        $this->createGroup($data->organization, [$data->member, $teammate]);
        $this->createGroup($data->organization, [$outsider], 'Other team');
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.member-groups.team-members', [$data->organization->getKey()]));

        $response->assertSuccessful();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$data->member->getKey(), $teammate->getKey()], $ids);
        $this->assertSame(['id', 'user_id', 'name'], array_keys($response->json('data.0')));
    }

    public function test_team_members_fails_for_employee_without_group(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.member-groups.team-members', [$data->organization->getKey()]));

        $response->assertForbidden();
    }

    // Viewing time entries of the team

    public function test_employee_in_group_can_aggregate_time_entries_of_teammate(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $teammate = $this->createMember($data->organization);
        $this->createGroup($data->organization, [$data->member, $teammate]);
        $start = Carbon::now()->subDays(2);
        TimeEntry::factory()->forOrganization($data->organization)->forMember($teammate)->startWithDuration($start, 100)->create();
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.time-entries.aggregate', [
            $data->organization->getKey(),
            'member_ids' => [$teammate->getKey()],
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.seconds', 100);
    }

    public function test_employee_in_group_sees_only_team_time_entries_without_member_filter(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $teammate = $this->createMember($data->organization);
        $outsider = $this->createMember($data->organization);
        $this->createGroup($data->organization, [$data->member, $teammate]);
        $start = Carbon::now()->subDays(2);
        TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->startWithDuration($start, 10)->create();
        TimeEntry::factory()->forOrganization($data->organization)->forMember($teammate)->startWithDuration($start, 100)->create();
        TimeEntry::factory()->forOrganization($data->organization)->forMember($outsider)->startWithDuration($start, 1000)->create();
        Passport::actingAs($data->user);

        $aggregate = $this->getJson(route('api.v1.time-entries.aggregate', [$data->organization->getKey()]));
        $index = $this->getJson(route('api.v1.time-entries.index', [$data->organization->getKey()]));

        $aggregate->assertSuccessful();
        $aggregate->assertJsonPath('data.seconds', 110);
        $index->assertSuccessful();
        $this->assertEqualsCanonicalizing(
            [$data->user->getKey(), $teammate->user_id],
            collect($index->json('data'))->pluck('user_id')->all()
        );
    }

    public function test_employee_in_group_cannot_view_time_entries_of_member_outside_their_groups(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $teammate = $this->createMember($data->organization);
        $outsider = $this->createMember($data->organization);
        $this->createGroup($data->organization, [$data->member, $teammate]);
        $this->createGroup($data->organization, [$outsider], 'Other team');
        Passport::actingAs($data->user);

        $byMemberIds = $this->getJson(route('api.v1.time-entries.aggregate', [
            $data->organization->getKey(),
            'member_ids' => [$teammate->getKey(), $outsider->getKey()],
        ]));
        $byMemberId = $this->getJson(route('api.v1.time-entries.index', [
            $data->organization->getKey(),
            'member_id' => $outsider->getKey(),
        ]));

        $byMemberIds->assertForbidden();
        $byMemberId->assertForbidden();
    }

    public function test_employee_without_group_still_cannot_view_other_time_entries(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $other = $this->createMember($data->organization);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.time-entries.aggregate', [
            $data->organization->getKey(),
            'member_ids' => [$other->getKey()],
        ]));

        $response->assertForbidden();
    }

    public function test_employee_in_group_does_not_see_billable_amounts_of_team(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $teammate = $this->createMember($data->organization);
        $this->createGroup($data->organization, [$data->member, $teammate]);
        TimeEntry::factory()->forOrganization($data->organization)->forMember($teammate)
            ->startWithDuration(Carbon::now()->subDays(2), 3600)->create(['billable' => true, 'billable_rate' => 10000]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.time-entries.aggregate', [$data->organization->getKey()]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.seconds', 3600);
        $response->assertJsonPath('data.cost', null);
    }

    // Members and organizations

    public function test_merging_members_moves_group_memberships(): void
    {
        $data = $this->createUserWithRole(Role::Admin);
        $placeholder = $this->createMember($data->organization, Role::Placeholder);
        $employee = $this->createMember($data->organization);
        $memberGroup = $this->createGroup($data->organization, [$placeholder]);

        app(MemberService::class)->assignOrganizationEntitiesToDifferentMember($data->organization, $placeholder, $employee);

        $this->assertSame([$employee->getKey()], $memberGroup->members()->pluck('members.id')->all());
    }
}
