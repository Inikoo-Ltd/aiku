<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 16:28:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tables = [
            'group_comms_stats',
            'organisation_comms_stats',
            'shop_comms_stats',
            'post_room_stats'
        ];

        $columns = [
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake(),
        ];

        foreach ($tables as $table) {
            $columnsToAdd = [];
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $columnsToAdd[] = $column;
                }
            }

            if (!empty($columnsToAdd)) {
                Schema::table($table, function (Blueprint $table) use ($columnsToAdd) {
                    foreach ($columnsToAdd as $column) {
                        $table->unsignedInteger($column)->default(0);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'group_comms_stats',
            'organisation_comms_stats',
            'shop_comms_stats',
            'post_room_stats'
        ];

        $columns = [
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake(),
        ];

        foreach ($tables as $table) {
            $existingColumns = array_filter($columns, function ($column) use ($table) {
                return Schema::hasColumn($table, $column);
            });

            if (!empty($existingColumns)) {
                Schema::table($table, function (Blueprint $table) use ($existingColumns) {
                    $table->dropColumn($existingColumns);
                });
            }
        }
    }
};
