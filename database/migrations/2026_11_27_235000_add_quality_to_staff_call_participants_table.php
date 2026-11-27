<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('staff_call_participants', function (Blueprint $table) {
            $table->jsonb('quality')->nullable()->comment('Running totals of what this person\'s browser measured, one sample per ping (PingStaffCall::addQualitySample)');
        });
    }

    public function down(): void
    {
        Schema::table('staff_call_participants', function (Blueprint $table) {
            $table->dropColumn('quality');
        });
    }
};
