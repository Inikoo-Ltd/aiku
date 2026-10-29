<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Comms\Mailshot\MailshotTypeEnum;
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
        return [
            'number_outboxes_type_'.OutboxCodeEnum::SUPPLIER_MAILING->snake(),
            'number_mailshots_type_'.MailshotTypeEnum::SUPPLIER->snake(),
        ];
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
