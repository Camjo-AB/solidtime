<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\MemberGroup;

use App\Http\Resources\PaginatedResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MemberGroupCollection extends ResourceCollection implements PaginatedResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = MemberGroupResource::class;
}
