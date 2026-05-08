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
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_items_waiting_crm')->default(0);
        });

        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->decimal('quantity_waiting_crm', 16, 6)->default(0);
            $table->boolean('has_waiting_crm')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropColumn([
                'number_items_waiting_crm'
            ]);
        });

        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->dropColumn([
                'quantity_waiting_crm',
                'has_waiting_crm'
            ]);
        });
    }
};
