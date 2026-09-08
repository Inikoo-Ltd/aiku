<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * results_count is what the customer was shown. Until hybrid search that also
     * answered "could anything match this at all", which is what the assortment-gap
     * reporting keys off. Hybrid breaks that equality on purpose - it returns related
     * products for queries keyword search cannot answer - so the gap signal needs its
     * own column before hybrid goes live, or the demand it surfaces stops being
     * recorded and cannot be reconstructed afterwards.
     */
    public function up(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->unsignedInteger('keyword_results_count')->default(0)->after('results_count');
            $table->unsignedInteger('vector_results_count')->default(0)->after('keyword_results_count');
        });

        DB::table('website_search_logs')->update([
            'keyword_results_count' => DB::raw('results_count'),
        ]);
    }


    public function down(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->dropColumn(['keyword_results_count', 'vector_results_count']);
        });
    }
};
