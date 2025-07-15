<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;


return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('customer_acquisition_sources', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');

            // Advertising Platform Identifiers
            $table->string('platform')->index(); // 'google_ads', 'meta_ads', 'microsoft_ads', etc.
            $table->string('tracking_id')->nullable(); // gclid, fbp, msclkid, etc.
            $table->json('utm_parameters')->nullable(); // utm_source, utm_medium, utm_campaign, etc.
            $table->string('referrer_url')->nullable();
            $table->string('landing_page')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            // Attribution Window & Lifecycle
            $table->timestamp('captured_at');
            $table->timestamp('expires_at')->index(); // Platform-specific expiration
            $table->integer('attribution_window_days'); // 90 for Google, 28 for Meta, etc.
            $table->boolean('is_active')->default(true)->index();

            // Metadata
            $table->json('data')->nullable(); // Additional platform-specific data
            $table->timestamps();


            $table->index(['customer_id', 'platform', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->unique(['customer_id', 'platform', 'tracking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_acquisition_sources');
    }
};
