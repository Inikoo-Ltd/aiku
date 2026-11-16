<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Helpers\Ticket\TicketTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS '.TicketTypeEnum::MERLIN->sequence());
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS '.TicketTypeEnum::MERLIN->sequence());
    }
};
