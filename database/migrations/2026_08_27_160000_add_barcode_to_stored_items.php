<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The barcode the goods themselves carry - typically the manufacturer's EAN13, which is not
     * known when the stored item is created and is nothing like the reference. Scan-to-pick reads
     * it alongside the reference; unlike the reference it is not unique per customer, two
     * customers can store the same retail product, so matching stays scoped to the return.
     */
    public function up(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }
};
