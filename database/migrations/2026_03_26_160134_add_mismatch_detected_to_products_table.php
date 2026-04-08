<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Mar 2026 00:04:26 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('mismatch_with_master_detected')->nullable();
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('mismatch_with_master_detected')->nullable();
        });
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('mismatch_with_seeder_detected')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('mismatch_with_master_detected');
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('mismatch_with_master_detected');
        });
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('mismatch_with_seeder_detected');
        });
    }
};
