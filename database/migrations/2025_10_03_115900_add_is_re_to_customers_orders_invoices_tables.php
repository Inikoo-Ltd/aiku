<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Oct 2025 12:00:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {

        if (!Schema::hasColumn('customers', 'is_re')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('is_re')->default(false)->comment('recargo de equivalencia');
            });
        }

        if (!Schema::hasColumn('orders', 'is_re')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('is_re')->default(false)->comment('recargo de equivalencia');
            });
        }

        if (!Schema::hasColumn('invoices', 'is_re')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->boolean('is_re')->default(false)->comment('recargo de equivalencia');
            });
        }
    }

    public function down(): void
    {

        if (Schema::hasColumn('customers', 'is_re')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('is_re');
            });
        }

        if (Schema::hasColumn('orders', 'is_re')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('is_re');
            });
        }

        if (Schema::hasColumn('invoices', 'is_re')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('is_re');
            });
        }
    }
};
