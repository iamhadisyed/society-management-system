<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdClick extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = ['ad_campaign_id', 'society_id', 'user_id', 'placement'];
}
