<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToSociety, HasAuditColumns, SoftDeletes;

    protected $fillable = ['society_id', 'name', 'department_head_id'];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_head_id');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_agents');
    }
}
