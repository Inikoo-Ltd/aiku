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
        Schema::table('website_visitors', function (Blueprint $table) {
            $table->string('traffic_source_type')->nullable();
            $table->string('traffic_source_reference')->nullable();
            $table->index(['website_id', 'traffic_source_type']);
        });
    }

    public function down(): void
    {
        Schema::table('website_visitors', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'traffic_source_type']);
            $table->dropColumn(['traffic_source_type', 'traffic_source_reference']);
        });
    }
};
