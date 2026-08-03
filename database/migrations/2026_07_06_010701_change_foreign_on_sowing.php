<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sowings', function ($table) {
            $table->dropForeign('sowings_return_id_foreign');
            $table->dropForeign('sowings_return_item_id_foreign');

            $table->foreign('return_id')->references('id')->on('return_delivery_notes');
            $table->foreign('return_item_id')->references('id')->on('return_delivery_note_items');
        });
    }


    public function down(): void
    {
        Schema::table('sowings', function ($table) {
            $table->dropForeign('sowings_return_id_foreign');
            $table->dropForeign('sowings_return_item_id_foreign');

            $table->foreign('return_id')->references('id')->on('returns');
            $table->foreign('return_item_id')->references('id')->on('return_items');
        });
    }
};
