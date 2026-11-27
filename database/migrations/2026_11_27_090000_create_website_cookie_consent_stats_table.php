<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 27 Nov 2026 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_cookie_consent_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('shown')->default(0)->comment('Times the banner was shown to a visitor with no saved choice, once per browser session');
            $table->unsignedInteger('accepted_all')->default(0)->comment('First choices granting every category');
            $table->unsignedInteger('rejected_all')->default(0)->comment('First choices granting no category');
            $table->unsignedInteger('partial')->default(0)->comment('First choices granting some categories');
            $table->unsignedInteger('analytics')->default(0)->comment('First choices granting Performance (analytics_storage)');
            $table->unsignedInteger('marketing')->default(0)->comment('First choices granting Targeting (ad_storage, ad_user_data, ad_personalization)');
            $table->unsignedInteger('preferences')->default(0)->comment('First choices granting Functionality (personalization_storage)');
            $table->timestampsTz();
            $table->unique(['website_id', 'date']);
        });

        DB::statement("COMMENT ON TABLE website_cookie_consent_stats IS 'Daily counts from our own cookie banner (INI-572), anonymous: no visitor, IP or cookie id is kept. Ignored = shown - (accepted_all + rejected_all + partial). Changes made later from Cookie settings are not counted'");
    }

    public function down(): void
    {
        Schema::dropIfExists('website_cookie_consent_stats');
    }
};
