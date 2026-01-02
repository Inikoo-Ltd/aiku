<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 03:25:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tables = [
            'org_post_room_stats',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'number_outboxes_type_reorder_reminder_2nd')) {
                    $table->unsignedInteger('number_outboxes_type_reorder_reminder_2nd')->default(0);
                }
                if (!Schema::hasColumn($tableName, 'number_outboxes_type_reorder_reminder_3rd')) {
                    $table->unsignedInteger('number_outboxes_type_reorder_reminder_3rd')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'org_post_room_stats',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columnsToDelete = [];
                if (Schema::hasColumn($tableName, 'number_outboxes_type_reorder_reminder_2nd')) {
                    $columnsToDelete[] = 'number_outboxes_type_reorder_reminder_2nd';
                }
                if (Schema::hasColumn($tableName, 'number_outboxes_type_reorder_reminder_3rd')) {
                    $columnsToDelete[] = 'number_outboxes_type_reorder_reminder_3rd';
                }
                if (!empty($columnsToDelete)) {
                    $table->dropColumn($columnsToDelete);
                }
            });
        }
    }
};
