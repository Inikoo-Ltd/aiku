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
        Schema::table('tiktok_user_has_orders', function (Blueprint $table) {
            $table->renameColumn('orderable_id', 'model_id');
            $table->renameColumn('orderable_type', 'model_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tiktok_user_has_orders', function (Blueprint $table) {
            $table->renameColumn('model_id', 'orderable_id');
            $table->renameColumn('model_type', 'orderable_type');
        });
    }
};
