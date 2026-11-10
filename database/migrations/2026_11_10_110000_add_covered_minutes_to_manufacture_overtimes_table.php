<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('manufacture_overtimes', 'approved_minutes')) {
            return;
        }

        Schema::table('manufacture_overtimes', function (Blueprint $table) {
            $table->unsignedSmallInteger('expected_minutes')->default(0)->comment('the shift less its unpaid break when decided; 0 on a day off');
            $table->unsignedSmallInteger('approved_minutes')->nullable()->comment('overtime approved: clocked beyond the shift when decided after the day, or the planned times when decided ahead');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_overtimes', function (Blueprint $table) {
            $table->dropColumn(['expected_minutes', 'approved_minutes']);
        });
    }
};
