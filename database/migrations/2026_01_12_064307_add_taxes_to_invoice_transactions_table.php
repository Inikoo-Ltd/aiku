<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->decimal('tax_amount', 16)->default(0);
            $table->boolean('is_tax_only')->default(false);
            $table->decimal('amount_total', 16)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
            $table->dropColumn('is_tax_only');
            $table->dropColumn('amount_total');
        });
    }
};
