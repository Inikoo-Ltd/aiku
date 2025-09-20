<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Aug 2025 08:03:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pay_detailed_status')) {
                $table->string('pay_detailed_status')->index()->nullable();
            }
        });
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'pay_detailed_status')) {
                $table->string('pay_detailed_status')->index()->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'pay_detailed_status')) {
                $table->dropColumn('pay_detailed_status');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'pay_detailed_status')) {
                $table->dropColumn('pay_detailed_status');
            }
        });
    }
};
