<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Mar 2026 05:34:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('amount_off', 16)->default(0);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('amount_off', 16)->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('amount_off');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('amount_off');
        });
    }
};
