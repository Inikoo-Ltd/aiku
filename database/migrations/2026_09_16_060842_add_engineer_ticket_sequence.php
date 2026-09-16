<?php

use App\Enums\Helpers\Ticket\TicketTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS '.TicketTypeEnum::ENGINEER->sequence());
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS '.TicketTypeEnum::ENGINEER->sequence());
    }
};
