<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 12:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('supplier_order_proposals', function (Blueprint $table) {
            $table->decimal('accepted_quantity_units', 16, 3)->nullable()->after('quantity_units')->comment('what the buyer put on the purchase order; quantity_units stays what the supplier proposed');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_order_proposals', function (Blueprint $table) {
            $table->dropColumn('accepted_quantity_units');
        });
    }
};
