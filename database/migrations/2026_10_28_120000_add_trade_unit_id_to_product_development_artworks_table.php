<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->unsignedInteger('trade_unit_id')->nullable();
            $table->foreign('trade_unit_id')->references('id')->on('trade_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->dropForeign(['trade_unit_id']);
            $table->dropColumn('trade_unit_id');
        });
    }
};
