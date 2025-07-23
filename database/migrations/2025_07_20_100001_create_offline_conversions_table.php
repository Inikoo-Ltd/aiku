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

            $table->unsignedBigInteger('customer_traffic_ad_id')->nullable();
            $table->foreign('customer_traffic_ad_id')->references('id')->on('customer_traffic_ads');

            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders');

            $table->string('platform_source')->index(); // manual/shopify/woocommerce/etc

            $table->decimal('revenue', 12, 4); // Conversion value
            $table->string('currency', 3)->default('USD');

            $table->string('advertising_platform')->index(); // e.g., Google Ads, Meta Ads, etc.
            $table->dateTimeTz('conversion_date')->index();
            $table->dateTimeTz('attribution_date')->index(); // Original click/visit date

            // Upload Status to Ad Platforms
            $table->boolean('uploaded_to_platform')->default(false)->index();
            $table->dateTimeTz('uploaded_at')->nullable()->index(); // When the conversion was uploaded to the platform
            $table->jsonb('upload_response')->nullable(); // Platform API response
            $table->string('upload_status')->default('pending')->index(); // pending, uploaded, failed

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_conversions');
    }
};
