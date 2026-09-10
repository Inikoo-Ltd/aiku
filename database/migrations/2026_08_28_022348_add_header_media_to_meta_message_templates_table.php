<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('meta_message_templates', function (Blueprint $table) {
            $table->unsignedInteger('header_media_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('meta_message_templates', function (Blueprint $table) {
            $table->dropColumn('header_media_id');
        });
    }
};
