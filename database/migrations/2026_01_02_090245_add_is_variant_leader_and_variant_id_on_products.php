<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('variant_id')
                ->nullable()
                ->index();
            $table->foreign('variant_id')->references('id')->on('variants');
            $table->boolean('is_variant_leader')->default('false');
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->unsignedSmallInteger('master_variant_id')
                ->nullable()
                ->index();
            $table->foreign('master_variant_id')->references('id')->on('master_variants');
            $table->boolean('is_variant_leader')->default('false');
        });
    }


    public function down(): void
    {
         Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropIndex(['variant_id']);
            $table->dropColumn(['variant_id', 'is_variant_leader']);
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropForeign(['master_variant_id']);
            $table->dropIndex(['master_variant_id']);
            $table->dropColumn(['master_variant_id', 'is_variant_leader']);
        });
    }
};
