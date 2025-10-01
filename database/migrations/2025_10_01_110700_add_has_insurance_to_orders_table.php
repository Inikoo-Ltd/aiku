<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Oct 2025 11:08:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('has_insurance')->nullable()->comment('Indicate if order has insurance');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('has_insurance');
        });
    }
};
