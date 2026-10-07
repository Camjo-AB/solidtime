<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\MemberGroup;

use App\Http\Resources\V1\BaseResource;
use App\Models\MemberGroup;
use Illuminate\Http\Request;

/**
 * @property MemberGroup $resource
 */
class MemberGroupResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, string|array<string>|null>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string $id ID */
            'id' => $this->resource->id,
            /** @var string $name Name of the team */
            'name' => $this->resource->name,
            /** @var array<string> $member_ids IDs of the members in the team */
            'member_ids' => $this->resource->members->pluck('id')->values()->all(),
            /** @var string $created_at When the team was created */
            'created_at' => $this->formatDateTime($this->resource->created_at),
            /** @var string $updated_at When the team was last updated */
            'updated_at' => $this->formatDateTime($this->resource->updated_at),
        ];
    }
}
