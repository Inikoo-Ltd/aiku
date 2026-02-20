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
        Schema::table('outboxes', function (Blueprint $table) {
            $table->integer('interval')->nullable()->comment('cool-down interval can be in hours, in minutes or etc.');
            $table->integer('threshold')->nullable()->comment('threshold for basket low stock or etc.');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outboxes', function (Blueprint $table) {
            $table->dropColumn('interval');
            $table->dropColumn('threshold');
        });
    }
};
