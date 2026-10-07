<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->unsignedInteger('index_under_master_variant')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('index_under_variant')->nullable();
        });

        Schema::table('variants', function (Blueprint $table) {
            $table->boolean('follow_master_variant_order')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('index_under_master_variant');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('index_under_variant');
        });

        Schema::table('variants', function (Blueprint $table) {
            $table->dropColumn('follow_master_variant_order');
        });
    }
};
