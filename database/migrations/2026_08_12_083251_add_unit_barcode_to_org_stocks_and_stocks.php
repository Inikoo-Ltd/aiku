<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->string('unit_barcode', 64)->nullable()->index();
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->index();
            $table->string('unit_barcode', 64)->nullable()->index();
        });

        DB::table('org_stocks')
            ->where('independent_barcode', false)
            ->whereNotNull('barcode')
            ->update([
                'unit_barcode' => DB::raw('barcode'),
                'barcode'      => null,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('unit_barcode');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn([
                'barcode',
                'unit_barcode',
            ]);
        });
    }
};
