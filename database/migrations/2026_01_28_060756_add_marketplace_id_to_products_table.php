<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Jan 2026 14:22:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string("marketplace_id")->index()->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string("marketplace_id")->index()->nullable();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string("marketplace_id")->index()->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn("marketplace_id");
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("marketplace_id");
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn("marketplace_id");
        });
    }
};
