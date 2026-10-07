<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['master_assets', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_back_order')->default(false);
                $table->boolean('is_made_to_order')->default(false);
                $table->decimal('pre_order_deposit_percentage', 5, 2)->nullable();
                $table->unsignedSmallInteger('pre_order_lead_time_days')->nullable();
                $table->unsignedInteger('max_quantity_per_order')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['master_assets', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['is_back_order', 'is_made_to_order', 'pre_order_deposit_percentage', 'pre_order_lead_time_days', 'max_quantity_per_order']);
            });
        }
    }
};
