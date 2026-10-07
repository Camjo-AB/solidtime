<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\MemberGroup;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization Organization from model binding
 */
class MemberGroupUpdateRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            // Name of the team
            'name' => [
                'required',
                'string',
                'min:1',
                'max:255',
            ],
            // Members of the team; replaces the current members
            'member_ids' => [
                'present',
                'array',
            ],
            'member_ids.*' => [
                'string',
                ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Member> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function getMemberIds(): array
    {
        /** @var array<string> $memberIds */
        $memberIds = $this->input('member_ids', []);

        return array_values(array_unique($memberIds));
    }
}
