<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Dec 2025 21:28:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'tax_category')) {
                $table->jsonb('tax_category')->default('{}');
            }
        });

        Schema::table('master_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('master_assets', 'tax_category')) {
                $table->jsonb('tax_category')->default('{}');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'tax_category')) {
                $table->dropColumn('tax_category');
            }
        });
        Schema::table('master_assets', function (Blueprint $table) {
            if (Schema::hasColumn('master_assets', 'tax_category')) {
                $table->dropColumn('tax_category');
            }
        });
    }
};
