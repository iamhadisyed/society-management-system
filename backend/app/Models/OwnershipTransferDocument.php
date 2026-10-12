<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipTransferDocument extends Model
{
    protected $fillable = ['ownership_transfer_id', 'label', 'file_path', 'uploaded_by'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(OwnershipTransfer::class, 'ownership_transfer_id');
    }
}
