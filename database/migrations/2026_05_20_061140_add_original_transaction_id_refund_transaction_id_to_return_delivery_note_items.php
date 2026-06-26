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
        Schema::table('return_delivery_note_items', function (Blueprint $table) {
            $table->unsignedBigInteger('original_transaction_id')->nullable();
            $table->foreign('original_transaction_id')->references('id')->on('transactions')->nullOnDelete();

            $table->unsignedBigInteger('refund_transaction_id')->nullable();
            $table->foreign('refund_transaction_id')->references('id')->on('invoice_transactions')->nullOnDelete();
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
            $table->dropForeign(['original_transaction_id']);
            $table->dropForeign(['refund_transaction_id']);
            $table->dropColumn(['original_transaction_id', 'refund_transaction_id']);
        });
    }
};
