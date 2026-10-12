<?php

namespace App\Http\Controllers\Api\V1\Units;

use App\Models\UnitCategory;

class UnitCategoryController extends LookupController
{
    protected string $model = UnitCategory::class;

    protected array $extraRules = ['description' => ['nullable', 'string']];
}
