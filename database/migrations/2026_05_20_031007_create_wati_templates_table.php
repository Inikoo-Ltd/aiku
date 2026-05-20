<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TODO: Check this migration to make sure it's correct
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('wati_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('waba_id')->nullable();
            $table->string('element_name')->nullable();
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->json('catalog_info')->nullable();
            $table->json('hsm')->nullable();
            $table->json('hsm_original')->nullable();
            $table->json('custom_params')->nullable();
            $table->string('status')->nullable();
            $table->json('language')->nullable();
            $table->timestamp('last_modified')->nullable();
            $table->string('type')->nullable();
            $table->json('header')->nullable();
            $table->text('body')->nullable();
            $table->text('body_original')->nullable();
            $table->string('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->string('buttons_type')->nullable();
            $table->json('carousel_cards')->nullable();
            $table->integer('expires_in')->default(0);
            $table->boolean('include_expiry_time')->default(false);
            $table->boolean('add_security_recommendation')->default(false);
            $table->boolean('is_url_btn_click_tracking_enabled')->default(false);
            $table->json('limited_time_offer')->nullable();
            $table->integer('quality')->default(0);
            $table->integer('creation_method')->default(0);
            $table->json('waba_context_ids')->nullable();
            $table->integer('shopify_trigger')->default(0);
            $table->boolean('include_coupon')->default(false);
            $table->integer('coupon_type')->default(0);
            $table->string('discount_value')->nullable();
            $table->string('coupon_code')->nullable();
            $table->boolean('enable_third_party_checkout')->default(false);
            $table->integer('attribution_days')->default(2);
            $table->integer('tracking_url_type')->default(0);
            $table->integer('tracking_url_version')->default(1);
            $table->string('client_name')->nullable();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('wati_templates');
    }
};
