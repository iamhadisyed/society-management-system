<?php

namespace App\Http\Controllers\Api\V1\Units;

use App\Models\Block;

class BlockController extends LookupController
{
    protected string $model = Block::class;
}
