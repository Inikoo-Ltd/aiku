<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 13:31:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_cash_on_delivery')->default(false)->after('can_dispatch');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('is_cash_on_delivery')->default(false)->after('delivery_locked');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_cash_on_delivery')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_cash_on_delivery');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('is_cash_on_delivery');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_cash_on_delivery');
        });
    }
};
