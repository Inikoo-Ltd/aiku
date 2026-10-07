<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 05 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ga_client_id')->nullable();
            $table->string('ga_session_id')->nullable();
            $table->timestampTz('ga_purchase_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ga_client_id', 'ga_session_id', 'ga_purchase_sent_at']);
        });
    }
};
