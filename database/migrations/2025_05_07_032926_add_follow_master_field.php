<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 11:30:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('follow_master')->default(true)->index();
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('follow_master')->default(true)->index();
        });
    }


    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('follow_master');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('follow_master');
        });
    }
};
