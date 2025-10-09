<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 10:36:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('has_extra_packing')->nullable();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('has_extra_packing')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('has_extra_packing');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('has_extra_packing');
        });
    }
};
