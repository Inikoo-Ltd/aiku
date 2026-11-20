<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-20 09:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('agent_notes_for_supplier')->nullable()->after('agent_notes');
            $table->text('supplier_notes')->nullable()->after('agent_notes_for_supplier');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['agent_notes_for_supplier', 'supplier_notes']);
        });
    }
};
