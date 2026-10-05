<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ci_runs', function (Blueprint $table) {
            $table->jsonb('test_results')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ci_runs', function (Blueprint $table) {
            $table->dropColumn('test_results');
        });
    }
};
