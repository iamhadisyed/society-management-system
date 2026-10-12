<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    protected $fillable = [
        'advertiser_id', 'owner_society_id', 'title', 'creative_path', 'link_url',
        'link_phone', 'link_whatsapp', 'starts_at', 'ends_at', 'scope',
        'target_society_ids', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'target_society_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(AdPlacement::class);
    }

    public function isCurrentlyRunning(): bool
    {
        return $this->is_active && now()->between($this->starts_at, $this->ends_at);
    }

    /** Campaigns visible to a given society: its own + running platform campaigns targeting it (or all). */
    public function scopeVisibleToSociety($query, int $societyId)
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(function ($q) use ($societyId) {
                $q->where('owner_society_id', $societyId)
                    ->orWhere(function ($q2) use ($societyId) {
                        $q2->where('scope', 'platform')
                            ->where(function ($q3) use ($societyId) {
                                $q3->whereNull('target_society_ids')
                                    ->orWhereJsonContains('target_society_ids', $societyId);
                            });
                    });
            });
    }
}
