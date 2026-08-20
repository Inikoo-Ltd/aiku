<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Aug 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Prospects keep their dispatched email counter on the model itself and recount it from the
     * pivot, so archiving would zero it like the stats tables. Same jsonb baseline shape as
     * comms stats, so the shared trait applies unchanged.
     */
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->jsonb('archived_dispatched_emails')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('archived_dispatched_emails');
        });
    }
};
