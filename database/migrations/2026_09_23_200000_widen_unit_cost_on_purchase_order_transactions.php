<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 6)->nullable()->comment('Price per unit agreed on this line, same currency as net_amount')->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 4)->nullable()->comment('Price per unit agreed on this line, same currency as net_amount')->change();
        });
    }
};
