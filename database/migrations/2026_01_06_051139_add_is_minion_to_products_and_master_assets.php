<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_minion_variant')->nullable()->index()->default(false);
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_minion_variant')->nullable()->index()->default(false);
        });

        DB::table('products')
            ->whereNotNull('variant_id')
            ->where(function ($query) {
                $query->where('is_variant_leader', false)
                      ->orWhereNull('is_variant_leader');
            })
            ->update(['is_minion_variant' => true]);

        DB::table('master_assets')
            ->whereNotNull('master_variant_id')
            ->where(function ($query) {
                $query->where('is_variant_leader', false)
                      ->orWhereNull('is_variant_leader');
            })
            ->update(['is_minion_variant' => true]);
    }


    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_minion_variant');
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('is_minion_variant');
        });
    }
};
