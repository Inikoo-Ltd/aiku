<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Models\Helpers\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE tickets ADD COLUMN search_vector tsvector, ADD COLUMN internal_search_vector tsvector');
        DB::statement('CREATE INDEX tickets_search_vector_idx ON tickets USING gin (search_vector)');
        DB::statement('CREATE INDEX tickets_internal_search_vector_idx ON tickets USING gin (internal_search_vector)');

        Ticket::refreshSearchVectors();
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets DROP COLUMN search_vector, DROP COLUMN internal_search_vector');
    }
};
