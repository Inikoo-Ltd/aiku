<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_developments', function (Blueprint $table) {
            $table->unsignedSmallInteger('production_id')->nullable()->index();
            $table->foreign('production_id')->references('id')->on('productions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_developments', function (Blueprint $table) {
            $table->dropForeign(['production_id']);
            $table->dropColumn('production_id');
        });
    }
};
