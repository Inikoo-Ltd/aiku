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
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->boolean('default_wholesale_location')->default(false);
            $table->boolean('default_dropshipping_location')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->dropColumn([
                'default_wholesale_location',
                'default_dropshipping_location'
            ]);
        });
    }
};
