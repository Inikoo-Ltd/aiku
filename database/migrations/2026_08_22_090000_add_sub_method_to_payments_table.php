<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 09:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('sub_method')->nullable()->index()->after('method');
        });

        DB::table('payments')
            ->whereIn('method', ['visa', 'mastercard', 'american express', 'amex'])
            ->update(['sub_method' => DB::raw('method'), 'method' => 'card']);
    }

    public function down(): void
    {
        DB::table('payments')
            ->where('method', 'card')
            ->whereNotNull('sub_method')
            ->update(['method' => DB::raw('sub_method')]);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('sub_method');
        });
    }
};
