<?php

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'group_comms_stats',
            'organisation_comms_stats',
            'shop_comms_stats',
            'post_room_stats'
        ];

        $columns = [
            'number_outboxes_type_' . OutboxCodeEnum::PRICE_CHANGE_NOTIFICATION->snake(),
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'group_comms_stats',
            'organisation_comms_stats',
            'shop_comms_stats',
            'post_room_stats'
        ];

        $columns = [
            'number_outboxes_type_' . OutboxCodeEnum::PRICE_CHANGE_NOTIFICATION->snake(),
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
