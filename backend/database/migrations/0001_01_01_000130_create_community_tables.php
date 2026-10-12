<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('title');
            $table->text('body_en')->nullable();
            $table->text('body_ur')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('target_type', ['all', 'blocks'])->default('all');
            $table->json('target_block_ids')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('push_sent')->default(false);
            Columns::audit($table);

            $table->index(['society_id', 'is_pinned']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('event_datetime');
            $table->string('venue')->nullable();
            $table->string('banner_path')->nullable();
            Columns::audit($table);
        });

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('response', ['going', 'not_going'])->default('going');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('title'); // e.g. Park Development - Phase 2
            $table->text('description')->nullable();
            Columns::audit($table);
        });

        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_album_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('info_desk_contacts', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. Security Office, Management Office, Hospital, Police
            $table->string('phone', 20);
            $table->string('category')->nullable();
            Columns::audit($table);
        });

        Schema::create('info_desk_documents', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('title'); // e.g. Society Bylaws, FAQs
            $table->string('file_path');
            $table->enum('category', ['bylaws', 'faq', 'form', 'other'])->default('other');
            Columns::audit($table);
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('question');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('results_visibility', ['always', 'after_end', 'admin_only'])->default('after_end');
            Columns::audit($table);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete(); // one vote per unit
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'unit_id']);
        });

        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['lost', 'found']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_returned')->default(false);
            $table->timestamps();
        });

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('category', ['item', 'rent', 'sale'])->default('item');
            $table->enum('status', ['pending_review', 'approved', 'rejected', 'closed'])->default('pending_review');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketplace_listing_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_listing_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
        });

        Schema::create('resident_directory_opt_ins', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_opted_in')->default(false);
            $table->timestamps();

            $table->unique(['society_id', 'user_id']);
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. Community Hall, Lawn, Gym, Guest Room
            $table->text('description')->nullable();
            $table->decimal('booking_fee', 12, 2)->default(0);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            Columns::audit($table);
        });

        Schema::create('facility_bookings', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booked_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            $table->decimal('fee', 12, 2)->default(0);
            $table->enum('fee_payment_mode', ['add_to_bill', 'pay_now'])->default('add_to_bill');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['facility_id', 'slot_start', 'slot_end']);
        });

        Schema::create('water_tanker_requests', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('requested_for');
            $table->decimal('charge', 12, 2)->default(0);
            $table->enum('status', ['requested', 'scheduled', 'delivered', 'cancelled'])->default('requested');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_tanker_requests');
        Schema::dropIfExists('facility_bookings');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('resident_directory_opt_ins');
        Schema::dropIfExists('marketplace_listing_photos');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('lost_found_items');
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('info_desk_documents');
        Schema::dropIfExists('info_desk_contacts');
        Schema::dropIfExists('gallery_photos');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('event_rsvps');
        Schema::dropIfExists('events');
        Schema::dropIfExists('notices');
    }
};
