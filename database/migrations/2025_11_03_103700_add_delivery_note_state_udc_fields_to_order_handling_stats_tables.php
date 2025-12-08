<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Nov 2025 14:53:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'shop_order_handling_stats',
            'organisation_order_handling_stats',
            'group_order_handling_stats',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // Counts
                $table->unsignedInteger('number_delivery_notes_state_unassigned')->default(0);
                $table->unsignedInteger('number_delivery_notes_state_dispatched')->default(0);
                $table->unsignedInteger('number_delivery_notes_state_cancelled')->default(0);

                // Weights
                $table->decimal('weight_delivery_notes_state_unassigned', 16)->default(0);
                $table->decimal('weight_delivery_notes_state_dispatched', 16)->default(0);
                $table->decimal('weight_delivery_notes_state_cancelled', 16)->default(0);

                // Items
                $table->unsignedInteger('number_items_delivery_notes_state_unassigned')->default(0);
                $table->unsignedInteger('number_items_delivery_notes_state_dispatched')->default(0);
                $table->unsignedInteger('number_items_delivery_notes_state_cancelled')->default(0);
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'shop_order_handling_stats',
            'organisation_order_handling_stats',
            'group_order_handling_stats',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'number_delivery_notes_state_unassigned',
                    'number_delivery_notes_state_dispatched',
                    'number_delivery_notes_state_cancelled',
                    'weight_delivery_notes_state_unassigned',
                    'weight_delivery_notes_state_dispatched',
                    'weight_delivery_notes_state_cancelled',
                    'number_items_delivery_notes_state_unassigned',
                    'number_items_delivery_notes_state_dispatched',
                    'number_items_delivery_notes_state_cancelled',
                ]);
            });
        }
    }
};
