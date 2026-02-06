<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('quantity_ordered', 16, 6)->nullable()->change();
            $table->decimal('quantity_bonus', 16, 6)->nullable()->default(0)->change();
            $table->decimal('quantity_dispatched', 16, 6)->nullable()->default(0)->change();
            $table->decimal('quantity_fail', 16, 6)->nullable()->default(0)->change();
            $table->decimal('quantity_cancelled', 16, 6)->nullable()->default(0)->change();
            $table->decimal('quantity_picked', 16, 6)->nullable()->change();
            $table->decimal('submitted_quantity_ordered', 16, 6)->default(0)->change();
        });

        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->decimal('quantity', 16, 6)->nullable()->change();
        });
    }


    public function down(): void
    {
        //
    }
};
