<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    use BelongsToSociety, HasAuditColumns;

    protected $fillable = [
        'society_id', 'title', 'description', 'nomination_start', 'nomination_end',
        'voting_start', 'voting_end', 'status', 'results_visibility', 'results_published',
    ];

    protected function casts(): array
    {
        return [
            'nomination_start' => 'datetime',
            'nomination_end' => 'datetime',
            'voting_start' => 'datetime',
            'voting_end' => 'datetime',
            'results_published' => 'boolean',
        ];
    }

    public function positions(): HasMany
    {
        return $this->hasMany(ElectionPosition::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ElectionCandidate::class);
    }

    public function canShowLiveResults(): bool
    {
        return $this->results_visibility === 'live' || $this->status === 'closed';
    }
}
