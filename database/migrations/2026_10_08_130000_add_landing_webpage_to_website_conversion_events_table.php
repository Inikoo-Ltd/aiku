<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_conversion_events', function (Blueprint $table) {
            $table->unsignedInteger('landing_webpage_id')->nullable();
            $table->foreign('landing_webpage_id')
                ->references('id')->on('webpages')
                ->onDelete('set null');
            $table->index(['landing_webpage_id', 'event_date']);
        });

        Schema::table('webpage_time_series_records', function (Blueprint $table) {
            $table->integer('checkouts')->default(0);
            $table->integer('purchases')->default(0);
            $table->decimal('revenue', 16)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('webpage_time_series_records', function (Blueprint $table) {
            $table->dropColumn(['checkouts', 'purchases', 'revenue']);
        });

        Schema::table('website_conversion_events', function (Blueprint $table) {
            $table->dropIndex(['landing_webpage_id', 'event_date']);
            $table->dropForeign(['landing_webpage_id']);
            $table->dropColumn('landing_webpage_id');
        });
    }
};
