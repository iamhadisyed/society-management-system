<?php

namespace App\Support\Migration;

use Illuminate\Database\Schema\Blueprint;

/**
 * Shared column groups so every tenant table gets society scoping,
 * audit columns and soft deletes consistently (see docs/decisions.md).
 */
class Columns
{
    public static function society(Blueprint $table): void
    {
        $table->foreignId('society_id')->constrained()->cascadeOnDelete();
    }

    public static function audit(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
        $table->softDeletes();
    }
}
