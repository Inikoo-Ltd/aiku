<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('variants', function (Blueprint $table) {
            $table->dropForeign(['leader_id']);

            $table->foreign('leader_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::table('variants', function (Blueprint $table) {
            $table->dropForeign(['leader_id']);

            $table->foreign('leader_id')
                ->references('id')
                ->on('assets')
                ->onDelete('cascade');
        });
    }
};
