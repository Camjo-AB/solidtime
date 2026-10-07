<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\MemberGroup;

use App\Http\Resources\V1\BaseResource;
use App\Models\Member;
use Illuminate\Http\Request;

/**
 * A member as seen by colleagues in the same team: only what the reporting needs,
 * no email, role or billable rate.
 *
 * @property Member $resource
 */
class TeamMemberResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string $id ID of the member */
            'id' => $this->resource->id,
            /** @var string $name Name of the member */
            'name' => $this->resource->user->name,
        ];
    }
}
