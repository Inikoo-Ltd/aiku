<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        $tables = ['pallet_return_stats', 'pallet_delivery_stats', 'location_stats', 'warehouse_area_stats'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'number_pallets_state_request_return')) {
                    $table->renameColumn('number_pallets_state_request_return', 'number_pallets_state_request_return_in_process');
                }

                if (! Schema::hasColumn($tableName, 'number_pallets_state_request_return_submitted')) {
                    $table->unsignedInteger('number_pallets_state_request_return_submitted')->default(0);
                }

                if (! Schema::hasColumn($tableName, 'number_pallets_state_request_return_confirmed')) {
                    $table->unsignedInteger('number_pallets_state_request_return_confirmed')->default(0);
                }
            });
        }

    }

    public function down(): void
    {
        Schema::table('pallet_return_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });

        Schema::table('pallet_delivery_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });

        Schema::table('location_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });

        Schema::table('warehouse_area_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });
    }
};
