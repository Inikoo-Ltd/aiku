<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_products')->default(0);
        });
        Schema::table('product_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_parent_webpages')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->dropColumn('number_products');
        });
        Schema::table('product_stats', function (Blueprint $table) {
            $table->dropColumn('number_parent_webpages');
        });
    }
};
