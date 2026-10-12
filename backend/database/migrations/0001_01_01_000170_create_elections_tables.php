<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('title'); // e.g. Managing Committee Elections 2026
            $table->text('description')->nullable();
            $table->dateTime('nomination_start');
            $table->dateTime('nomination_end');
            $table->dateTime('voting_start');
            $table->dateTime('voting_end');
            $table->enum('status', ['draft', 'nominations_open', 'voting_open', 'closed', 'cancelled'])->default('draft');
            $table->enum('results_visibility', ['after_close', 'live'])->default('after_close');
            $table->boolean('results_published')->default(false);
            Columns::audit($table);
        });

        Schema::create('election_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('title'); // e.g. President, General Secretary, Finance Secretary
            $table->unsignedTinyInteger('seats_available')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('election_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // nominee (resident)
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('symbol')->nullable(); // election symbol (text/emoji/icon key)
            $table->text('manifesto')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('status', ['nominated', 'approved', 'rejected', 'withdrawn'])->default('nominated');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['election_position_id', 'user_id']);
        });

        Schema::create('election_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete(); // one vote per unit per position
            $table->timestamp('voted_at');

            // No per-vote soft delete / update: a cast ballot is immutable.
            $table->unique(['election_position_id', 'unit_id']);
        });

        Schema::create('election_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('votes_count')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('certified_at')->nullable();
            $table->timestamps();

            // Explicit short name: the auto-generated
            // "election_results_election_position_id_election_candidate_id_unique"
            // is 66 chars, over MySQL's 64-char identifier limit.
            $table->unique(['election_position_id', 'election_candidate_id'], 'election_results_position_candidate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_results');
        Schema::dropIfExists('election_votes');
        Schema::dropIfExists('election_candidates');
        Schema::dropIfExists('election_positions');
        Schema::dropIfExists('elections');
    }
};
