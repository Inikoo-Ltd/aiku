<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 11:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->unsignedInteger('paid_overtime_seconds')->nullable()->comment('under the paid time rule: the time clocked outside the shift that approved overtime covers; null = no rule on the day');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->dropColumn('paid_overtime_seconds');
        });
    }
};
