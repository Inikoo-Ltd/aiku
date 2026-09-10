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
        Schema::table('meta_chat_messages', function (Blueprint $table) {
            $table->unsignedInteger('replied_to_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('meta_chat_messages', function (Blueprint $table) {
            $table->dropColumn('replied_to_id');
        });
    }
};
