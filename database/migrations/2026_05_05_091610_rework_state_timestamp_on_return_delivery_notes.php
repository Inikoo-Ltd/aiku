<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    // php artisan repair:missing_fixed_web_blocks_in_families_webpages sk

    public function up(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->dropColumn([
                'queued_at',
                'handling_at',
                'picked_at',
                'received_at',
                'cancelled_at',
            ]);

            $table->dateTimeTz('returning_at')->nullable();
            $table->dateTimeTz('returned_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->dropColumn([
                'returning_at',
                'returned_at',
                'cancelled_at',
            ]);

            $table->dateTimeTz('queued_at')->nullable();
            $table->dateTimeTz('handling_at')->nullable();
            $table->dateTimeTz('picked_at')->nullable();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
        });
    }
};
