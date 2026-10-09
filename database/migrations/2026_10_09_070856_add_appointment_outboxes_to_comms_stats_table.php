<?php

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'group_comms_stats',
        'organisation_comms_stats',
        'shop_comms_stats',
        'post_room_stats',
        'org_post_room_stats',
    ];

    private function columns(): array
    {
        return array_map(
            fn (OutboxCodeEnum $code) => 'number_outboxes_type_'.$code->snake(),
            [
                OutboxCodeEnum::APPOINTMENT_REQUESTED,
                OutboxCodeEnum::APPOINTMENT_ACCEPTED,
                OutboxCodeEnum::APPOINTMENT_DECLINED,
                OutboxCodeEnum::APPOINTMENT_RESCHEDULED,
                OutboxCodeEnum::APPOINTMENT_CANCELLED,
            ]
        );
    }

    public function up(): void
    {
        foreach ($this->tables as $table) {
            foreach ($this->columns() as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $table) use ($column) {
                        $table->unsignedInteger($column)->default(0);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            foreach ($this->columns() as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
