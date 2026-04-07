<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 02:31:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->boolean('movements_fixed')->index()->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('movements_fixed');
        });
    }
};
