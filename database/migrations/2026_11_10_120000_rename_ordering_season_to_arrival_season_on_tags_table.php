<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->renameColumn('order_from_month', 'arrive_from_month');
            $table->renameColumn('order_to_month', 'arrive_to_month');
        });
        DB::statement("comment on column tags.arrive_from_month is 'seasonal tag: first month (1-12) its products should arrive in; ordering suggestions add the lead time'");
        DB::statement("comment on column tags.arrive_to_month is 'seasonal tag: last month (1-12) its products should arrive in'");
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->renameColumn('arrive_from_month', 'order_from_month');
            $table->renameColumn('arrive_to_month', 'order_to_month');
        });
    }
};
