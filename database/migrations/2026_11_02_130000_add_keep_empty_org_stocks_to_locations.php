<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('keep_empty_org_stocks')->default(false)->comment('empty SKOs are never removed from this location automatically');
        });

        DB::table('locations')
            ->whereIn('code', ['Production-Room', 'Production-RELAB'])
            ->whereIn('organisation_id', DB::table('organisations')->where('slug', 'sk')->select('id'))
            ->update(['keep_empty_org_stocks' => true]);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('keep_empty_org_stocks');
        });
    }
};
