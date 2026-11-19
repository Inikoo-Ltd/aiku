<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 16:02:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_step_tiers', function (Blueprint $table) {
            $table->string('label')->nullable()->comment('the step label: the same task used more than once in a recipe has its own tiers per label');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_step_tiers', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
