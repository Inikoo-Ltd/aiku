<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Tue, 21 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('preferred_shipping_id')->nullable()->index();
            $table->foreign('preferred_shipping_id')->references('id')->on('preferred_shippings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['preferred_shipping_id']);
            $table->dropColumn('preferred_shipping_id');
        });
    }
};
