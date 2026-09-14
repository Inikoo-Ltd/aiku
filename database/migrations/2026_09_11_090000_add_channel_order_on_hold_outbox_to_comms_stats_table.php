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

    private function column(): string
    {
        return 'number_outboxes_type_'.OutboxCodeEnum::CHANNEL_ORDER_ON_HOLD->snake();
    }

    public function up(): void
    {
        $column = $this->column();
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->unsignedInteger($column)->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        $column = $this->column();
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
