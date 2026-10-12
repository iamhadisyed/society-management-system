<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietySubscription extends Model
{
    protected $fillable = ['society_id', 'subscription_plan_id', 'start_date', 'end_date', 'status', 'price_at_signup'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'price_at_signup' => 'decimal:2'];
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
