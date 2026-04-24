<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 24 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('batch_codes', function (Blueprint $table) {
            $table->unsignedInteger('number_delivery_notes')->default(0)->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('batch_codes', function (Blueprint $table) {
            $table->dropColumn('number_delivery_notes');
        });
    }
};
