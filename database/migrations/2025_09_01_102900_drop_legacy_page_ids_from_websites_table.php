<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sept 2025 10:39:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('websites')) {
            Schema::table('websites', function (Blueprint $table) {
                $columns = [
                    'catalogue_id',
                    'products_id',
                    'login_id',
                    'register_id',
                    'basket_id',
                    'checkout_id',
                    'call_back_id',
                    'appointment_id',
                    'pricing_id',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('websites', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('websites')) {
            Schema::table('websites', function (Blueprint $table) {
                // Re-create columns with original definition: unsignedInteger, nullable, indexed
                $toReAdd = [
                    'catalogue_id',
                    'products_id',
                    'login_id',
                    'register_id',
                    'basket_id',
                    'checkout_id',
                    'call_back_id',
                    'appointment_id',
                    'pricing_id',
                ];

                foreach ($toReAdd as $column) {
                    if (!Schema::hasColumn('websites', $column)) {
                        $table->unsignedInteger($column)->nullable()->index();
                    }
                }
            });
        }
    }
};
