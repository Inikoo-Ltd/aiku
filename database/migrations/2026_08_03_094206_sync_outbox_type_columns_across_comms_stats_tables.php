<?php

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Earlier migrations added each new outbox type to four of the five stats tables and left
     * org_post_room_stats behind, so OrgPostRoomHydrateOutboxes wrote columns that did not exist.
     * Driving the column list off the enum keeps every table complete whatever it was missing.
     */
    private const TABLES = [
        'group_comms_stats',
        'organisation_comms_stats',
        'shop_comms_stats',
        'post_room_stats',
        'org_post_room_stats',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            $missingColumns = [];
            foreach (OutboxCodeEnum::cases() as $case) {
                $column = 'number_outboxes_type_'.$case->snake();
                if (!Schema::hasColumn($tableName, $column)) {
                    $missingColumns[] = $column;
                }
            }

            if (!empty($missingColumns)) {
                Schema::table($tableName, function (Blueprint $table) use ($missingColumns) {
                    foreach ($missingColumns as $column) {
                        $table->unsignedInteger($column)->default(0);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // ponytail: no drop, this migration cannot tell which columns it added from those already present
    }
};
