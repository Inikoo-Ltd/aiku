<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('group_fulfilment_stats', 'number_pallets_state_not_picked')) {
            Schema::table('group_fulfilment_stats', function (Blueprint $table): void {
                $table->integer('number_pallets_state_not_picked')->default(0);
            });
        }

        if (!Schema::hasColumn('organisation_fulfilment_stats', 'number_pallets_state_not_picked')) {
            Schema::table('organisation_fulfilment_stats', function (Blueprint $table): void {
                $table->integer('number_pallets_state_not_picked')->default(0);
            });
        }

        if (!Schema::hasColumn('warehouse_stats', 'number_pallets_state_not_picked')) {
            Schema::table('warehouse_stats', function (Blueprint $table): void {
                $table->integer('number_pallets_state_not_picked')->default(0);
            });
        }

        if (!Schema::hasColumn('location_stats', 'number_pallets_state_not_picked')) {
            Schema::table('location_stats', function (Blueprint $table): void {
                $table->integer('number_pallets_state_not_picked')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('group_fulfilment_stats', 'number_pallets_state_not_picked')) {
            Schema::table('group_fulfilment_stats', function (Blueprint $table): void {
                $table->dropColumn(['number_pallets_state_not_picked']);
            });
        }

        if (Schema::hasColumn('organisation_fulfilment_stats', 'number_pallets_state_not_picked')) {
            Schema::table('organisation_fulfilment_stats', function (Blueprint $table): void {
                $table->dropColumn(['number_pallets_state_not_picked']);
            });
        }

        if (Schema::hasColumn('warehouse_stats', 'number_pallets_state_not_picked')) {
            Schema::table('warehouse_stats', function (Blueprint $table): void {
                $table->dropColumn(['number_pallets_state_not_picked']);
            });
        }

        if (Schema::hasColumn('location_stats', 'number_pallets_state_not_picked')) {
            Schema::table('location_stats', function (Blueprint $table): void {
                $table->dropColumn(['number_pallets_state_not_picked']);
            });
        }
    }
};
