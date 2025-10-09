<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 00:17:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'is_premium_dispatch')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('is_premium_dispatch')->default(false);
            });
        }

        if (!Schema::hasColumn('delivery_notes', 'is_premium_dispatch')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->boolean('is_premium_dispatch')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'is_premium_dispatch')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('is_premium_dispatch');
            });
        }

        if (Schema::hasColumn('delivery_notes', 'is_premium_dispatch')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->dropColumn('is_premium_dispatch');
            });
        }
    }
};
