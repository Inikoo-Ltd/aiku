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
        Schema::create('offline_conversions', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedBigInteger('customer_acquisition_source_id')->nullable();
            $table->foreign('customer_acquisition_source_id')->references('id')->on('customer_acquisition_sources');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders');


            $table->string('external_order_id')->nullable(); // Shopify/WooCommerce order ID
            $table->string('platform_source')->nullable(); // 'shopify', 'woocommerce', 'manual', etc.

            $table->decimal('revenue', 12, 4); // Conversion value
            $table->string('currency', 3)->default('USD');
            $table->decimal('grp_exchange', 12, 4)->nullable();
            $table->decimal('org_exchange', 12, 4)->nullable();

            // Attribution Data
            $table->string('advertising_platform')->nullable(); // google_ads, meta_ads, etc.
            $table->string('tracking_id')->nullable(); // gclid, fbp, etc.
            $table->timestamp('conversion_date');
            $table->timestamp('attribution_date')->nullable(); // Original click/visit date

            // Upload Status to Ad Platforms
            $table->boolean('uploaded_to_platform')->default(false);
            $table->timestamp('uploaded_at')->nullable();
            $table->json('upload_response')->nullable(); // Platform API response
            $table->string('upload_status')->default('pending'); // pending, uploaded, failed

            $table->index(['advertising_platform']);
            $table->index(['upload_status', 'uploaded_to_platform']);
            $table->index(['conversion_date', 'within_attribution_window']);
            $table->index(['external_order_id', 'platform_source']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_conversions');
    }
};
