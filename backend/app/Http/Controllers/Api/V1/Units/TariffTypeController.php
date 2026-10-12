<?php

namespace App\Http\Controllers\Api\V1\Units;

use App\Models\TariffType;

class TariffTypeController extends LookupController
{
    protected string $model = TariffType::class;
}
