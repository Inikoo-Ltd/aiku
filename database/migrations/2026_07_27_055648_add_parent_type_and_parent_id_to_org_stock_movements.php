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
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->string('parent_type')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->index(['parent_id', 'parent_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropColumn([
                'parent_type',
                'parent_id'
            ]);
        });
    }
};
