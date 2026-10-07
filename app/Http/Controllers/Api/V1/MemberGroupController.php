<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\MemberGroup\MemberGroupIndexRequest;
use App\Http\Requests\V1\MemberGroup\MemberGroupStoreRequest;
use App\Http\Requests\V1\MemberGroup\MemberGroupUpdateRequest;
use App\Http\Resources\V1\MemberGroup\MemberGroupCollection;
use App\Http\Resources\V1\MemberGroup\MemberGroupResource;
use App\Http\Resources\V1\MemberGroup\TeamMemberResource;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Organization;
use App\Service\MemberGroupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Member groups are called "teams" in the UI. Employees in a team can view the time entries of
 * the other members of their teams in the reporting.
 */
class MemberGroupController extends Controller
{
    protected function checkPermission(Organization $organization, string $permission, ?MemberGroup $memberGroup = null): void
    {
        parent::checkPermission($organization, $permission);
        if ($memberGroup !== null && $memberGroup->organization_id !== $organization->getKey()) {
            throw new AuthorizationException('Member group does not belong to organization');
        }
    }

    /**
     * Get member groups (teams)
     *
     * @return MemberGroupCollection<MemberGroupResource>
     *
     * @operationId getMemberGroups
     *
     * @throws AuthorizationException
     */
    public function index(Organization $organization, MemberGroupIndexRequest $request): MemberGroupCollection
    {
        $this->checkPermission($organization, 'member-groups:view');

        $memberGroups = MemberGroup::query()
            ->whereBelongsTo($organization, 'organization')
            ->with(['members'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(config('app.pagination_per_page_default'));

        return new MemberGroupCollection($memberGroups);
    }

    /**
     * Create member group (team)
     *
     * @throws AuthorizationException
     *
     * @operationId createMemberGroup
     */
    public function store(Organization $organization, MemberGroupStoreRequest $request): MemberGroupResource
    {
        $this->checkPermission($organization, 'member-groups:create');

        $memberGroup = new MemberGroup;
        $memberGroup->name = $request->input('name');
        $memberGroup->organization()->associate($organization);
        $memberGroup->save();
        $memberGroup->members()->sync($request->getMemberIds());
        $memberGroup->load('members');

        return new MemberGroupResource($memberGroup);
    }

    /**
     * Update member group (team)
     *
     * @throws AuthorizationException
     *
     * @operationId updateMemberGroup
     */
    public function update(Organization $organization, MemberGroup $memberGroup, MemberGroupUpdateRequest $request): MemberGroupResource
    {
        $this->checkPermission($organization, 'member-groups:update', $memberGroup);

        $memberGroup->name = $request->input('name');
        $memberGroup->save();
        $memberGroup->members()->sync($request->getMemberIds());
        $memberGroup->load('members');

        return new MemberGroupResource($memberGroup);
    }

    /**
     * Delete member group (team)
     *
     * @throws AuthorizationException
     *
     * @operationId deleteMemberGroup
     */
    public function destroy(Organization $organization, MemberGroup $memberGroup): JsonResponse
    {
        $this->checkPermission($organization, 'member-groups:delete', $memberGroup);

        $memberGroup->delete();

        return response()->json(null, 204);
    }

    /**
     * Get the members of my teams
     *
     * Members whose time entries the current user can view through their teams, including themself.
     * Only returns ID and name.
     *
     * @return AnonymousResourceCollection<TeamMemberResource>
     *
     * @operationId getTeamMembers
     *
     * @throws AuthorizationException
     */
    public function teamMembers(Organization $organization, MemberGroupService $memberGroupService): AnonymousResourceCollection
    {
        $this->checkPermission($organization, 'time-entries:view:team');

        $members = Member::query()
            ->whereBelongsTo($organization, 'organization')
            ->whereIn('id', $memberGroupService->visibleMemberIds($this->member($organization)))
            ->with(['user'])
            ->get()
            ->sortBy(fn (Member $member): string => $member->user->name)
            ->values();

        return TeamMemberResource::collection($members);
    }
}
