<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advertiser extends Model
{
    use SoftDeletes;

    protected $fillable = ['society_id', 'business_name', 'contact_phone', 'contact_email', 'category'];

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }
}
