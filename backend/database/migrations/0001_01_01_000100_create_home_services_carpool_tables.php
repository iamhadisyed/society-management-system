<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. AC Technician, Plumber, Electrician
            $table->string('icon')->nullable();
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('photo_path')->nullable();
            $table->string('phone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('service_area')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->decimal('listing_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            Columns::audit($table);
        });

        Schema::create('service_provider_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['service_provider_id', 'user_id']);
        });

        Schema::create('ride_offers', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('offered_by')->constrained('users')->cascadeOnDelete();
            $table->string('from_location');
            $table->string('to_location');
            $table->time('departure_time');
            $table->unsignedTinyInteger('available_seats');
            $table->json('recurring_days')->nullable(); // ['mon','tue',...]
            $table->decimal('cost_share', 10, 2)->nullable();
            $table->string('vehicle')->nullable();
            $table->string('driver_name')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamps();
        });

        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('from_location');
            $table->string('to_location');
            $table->dateTime('needed_at');
            $table->enum('status', ['open', 'fulfilled', 'cancelled'])->default('open');
            $table->timestamps();
        });

        Schema::create('ride_seat_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seats')->default(1);
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();

            $table->unique(['ride_offer_id', 'requested_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_seat_requests');
        Schema::dropIfExists('ride_requests');
        Schema::dropIfExists('ride_offers');
        Schema::dropIfExists('service_provider_reviews');
        Schema::dropIfExists('service_providers');
        Schema::dropIfExists('service_categories');
    }
};
