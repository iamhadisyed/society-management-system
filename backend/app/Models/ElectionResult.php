<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionResult extends Model
{
    protected $fillable = [
        'election_id', 'election_position_id', 'election_candidate_id',
        'votes_count', 'is_winner', 'certified_by', 'certified_at',
    ];

    protected function casts(): array
    {
        return ['is_winner' => 'boolean', 'certified_at' => 'datetime'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ElectionCandidate::class, 'election_candidate_id');
    }
}
