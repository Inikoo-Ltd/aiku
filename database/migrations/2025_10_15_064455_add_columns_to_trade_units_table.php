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
        Schema::table('trade_units', function (Blueprint $table) {
            $table->string('ufi_number')->nullable();
            $table->string('scpn_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn('ufi_number');
            $table->dropColumn('scpn_number');
        });
    }
};
