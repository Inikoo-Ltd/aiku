<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->dateTimeTz('preparing_at')->nullable();
            $table->decimal('quantity_to_produce', 16, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->dropColumn(['preparing_at', 'quantity_to_produce']);
        });
    }
};
