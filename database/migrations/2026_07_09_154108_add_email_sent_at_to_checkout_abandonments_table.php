<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('checkout_abandonments', function (Blueprint $table) {
            $table->timestampTz('email_sent_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('checkout_abandonments', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });
    }
};
