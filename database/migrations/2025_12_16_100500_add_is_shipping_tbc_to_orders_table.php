<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Dec 2025 10:06:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_shipping_tbc')) {
                $table->boolean('is_shipping_tbc')->default(false)->index();
            }
            if (!Schema::hasColumn('orders', 'shipping_tbc_amount')) {
                $table->decimal('shipping_tbc_amount', 16)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_shipping_tbc')) {
                $table->dropColumn('is_shipping_tbc');
            }
            if (Schema::hasColumn('orders', 'shipping_tbc_amount')) {
                $table->dropColumn('shipping_tbc_amount');
            }
        });
    }
};
