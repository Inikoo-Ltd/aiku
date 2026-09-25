<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('rejected_notes')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('ufi_number')->nullable()->change();
        });

        Schema::table('trade_units', function (Blueprint $table) {
            $table->text('ufi_number')->nullable()->change();
            $table->text('scpn_number')->nullable()->change();
        });
    }
};
