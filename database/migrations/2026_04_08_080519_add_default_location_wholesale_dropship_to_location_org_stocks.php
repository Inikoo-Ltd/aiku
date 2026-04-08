<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->boolean('default_wholesale_picking_location')->default(false);
            $table->boolean('default_dropshipping_picking_location')->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->dropColumn([
                'default_wholesale_location',
                'default_dropshipping_location'
            ]);
        });
    }
};
