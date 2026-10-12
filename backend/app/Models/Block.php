<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use BelongsToSociety, HasAuditColumns, SoftDeletes;

    protected $fillable = ['society_id', 'name'];

    public function streets(): HasMany
    {
        return $this->hasMany(Street::class);
    }
}
