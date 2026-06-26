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
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_id')->nullable();
            $table->foreign('refund_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
            $table->dropColumn(['refund_id']);
        });
    }
};
