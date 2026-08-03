<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('fiscal_name')->nullable()->after('company_name');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('fiscal_name')->nullable()->after('customer_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('fiscal_name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('fiscal_name');
        });
    }
};
