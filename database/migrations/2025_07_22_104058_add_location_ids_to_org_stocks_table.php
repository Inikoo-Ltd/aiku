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
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->unsignedInteger('picking_location_id')->nullable()->index();
            $table->foreign('picking_location_id')->references('id')->on('locations')->nullOnDelete();
            $table->unsignedInteger('picking_dropshipping_location_id')->nullable()->index();
            $table->foreign('picking_dropshipping_location_id')->references('id')->on('locations')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropForeign(['picking_location_id']);
            $table->dropForeign(['picking_dropshipping_location_id']);

            $table->dropColumn(['picking_location_id', 'picking_dropshipping_location_id']);
        });
    }
};
