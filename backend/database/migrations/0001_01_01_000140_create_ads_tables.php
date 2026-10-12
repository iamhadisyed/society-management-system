<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('society_id')->nullable(); // null = platform-level advertiser
            $table->string('business_name');
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('category')->nullable(); // hotel, food point, ...
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('owner_society_id')->nullable(); // null = platform-run campaign
            $table->string('title');
            $table->string('creative_path'); // image
            $table->string('link_url')->nullable();
            $table->string('link_phone', 20)->nullable();
            $table->string('link_whatsapp', 20)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('scope', ['platform', 'society'])->default('society');
            $table->json('target_society_ids')->nullable(); // for platform-scoped campaigns targeting select societies
            $table->boolean('is_active')->default(true);
            Columns::audit($table);
        });

        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->enum('placement', ['home_carousel', 'ad_list', 'bill_pdf']);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ad_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('society_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('placement');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ad_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('society_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('placement');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_clicks');
        Schema::dropIfExists('ad_impressions');
        Schema::dropIfExists('ad_placements');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('advertisers');
    }
};
