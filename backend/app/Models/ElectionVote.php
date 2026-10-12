<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['election_id', 'election_position_id', 'election_candidate_id', 'unit_id', 'voted_at'];

    protected function casts(): array
    {
        return ['voted_at' => 'datetime'];
    }
}
