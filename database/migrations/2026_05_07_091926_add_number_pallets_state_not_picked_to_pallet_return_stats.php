<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pallet_return_stats', 'number_pallets_state_not_picked')) {
            Schema::table('pallet_return_stats', function (Blueprint $table): void {
                $table->integer('number_pallets_state_not_picked')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pallet_return_stats', 'number_pallets_state_not_picked')) {
            Schema::table('pallet_return_stats', function (Blueprint $table): void {
                $table->dropColumn('number_pallets_state_not_picked');
            });
        }
    }
};
