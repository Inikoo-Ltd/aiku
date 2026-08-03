<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Jul 2026 11:00:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('allowance_type')->nullable()->index()->comment('Used for performance, to avoid load offer_allowances');
        });
    }


    public function down()
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('allowance_type');
        });
    }
};
