<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('org_stocks', 'has_wrong_weight')) {
                $table->boolean('has_wrong_weight')->default(false)->index();
            }
            if (!Schema::hasColumn('org_stocks', 'measured_weight')) {
                $table->unsignedInteger('measured_weight')->nullable()->comment('grams per SKO, median of weighed single-SKO parcels minus the box');
            }
            if (!Schema::hasColumn('org_stocks', 'measured_weight_parcels')) {
                $table->unsignedSmallInteger('measured_weight_parcels')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            foreach (['has_wrong_weight', 'measured_weight', 'measured_weight_parcels'] as $column) {
                if (Schema::hasColumn('org_stocks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
