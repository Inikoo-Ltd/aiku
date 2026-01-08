<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    public function up(): void
    {
        DB::table('products')
            ->whereNull('is_minion_variant')
            ->update(['is_minion_variant' => false]);

        DB::table('master_assets')
            ->whereNull('is_minion_variant')
            ->update(['is_minion_variant' => false]);

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_minion_variant')
                ->default(false)
                ->nullable(false)
                ->change();
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_minion_variant')
                ->default(false)
                ->nullable(false)
                ->change();
        });
    }


    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_minion_variant')
                ->nullable()
                ->default(null)
                ->change();
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_minion_variant')
                ->nullable()
                ->default(null)
                ->change();
        });
    }
};
