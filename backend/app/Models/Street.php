<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Street extends Model
{
    use BelongsToSociety, HasAuditColumns, SoftDeletes;

    protected $fillable = ['society_id', 'block_id', 'name'];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
