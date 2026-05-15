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
        Schema::table('return_delivery_note_items', function (Blueprint $table) {
            $table->dropColumn([
                'total_item_lost'
            ]);
            $table->decimal('total_expected_qty', 16, 6)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('return_delivery_note_items', function (Blueprint $table) {
            $table->dropColumn([
                'total_expected_qty'
            ]);
            $table->decimal('total_item_lost', 16, 6)->default(0);
        });
    }
};
