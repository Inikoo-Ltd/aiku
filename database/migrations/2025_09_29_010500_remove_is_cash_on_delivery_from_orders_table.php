<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Sept 2025 01:09:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'is_cash_on_delivery')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('is_cash_on_delivery');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'is_cash_on_delivery')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('is_cash_on_delivery')->default(false);
            });
        }
    }
};
