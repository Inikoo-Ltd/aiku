<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 21:43:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_has_trolleys', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_note_has_trolleys', 'state')) {
                $table->dropColumn('state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_has_trolleys', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_note_has_trolleys', 'state')) {
                $table->string('state')->nullable()->index();
            }
        });
    }
};
