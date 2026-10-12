<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitCategory extends Model
{
    use BelongsToSociety, HasAuditColumns, SoftDeletes;

    protected $fillable = ['society_id', 'name', 'description'];
}
