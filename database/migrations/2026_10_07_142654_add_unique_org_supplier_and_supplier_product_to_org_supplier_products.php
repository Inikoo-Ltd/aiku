<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 14:26:54 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $duplicateGroups = DB::table('org_supplier_products')
            ->groupBy('org_supplier_id', 'supplier_product_id')
            ->havingRaw('count(*) > 1')
            ->selectRaw('1')
            ->get()
            ->count();

        if ($duplicateGroups > 0) {
            throw new RuntimeException("org_supplier_products has $duplicateGroups duplicated (org_supplier_id, supplier_product_id) groups, run the INI-032 merge first");
        }

        DB::statement('ALTER TABLE org_supplier_products ALTER COLUMN org_supplier_id SET NOT NULL');

        Schema::table('org_supplier_products', function (Blueprint $table) {
            $table->unique(['org_supplier_id', 'supplier_product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('org_supplier_products', function (Blueprint $table) {
            $table->dropUnique(['org_supplier_id', 'supplier_product_id']);
        });

        DB::statement('ALTER TABLE org_supplier_products ALTER COLUMN org_supplier_id DROP NOT NULL');
    }
};
