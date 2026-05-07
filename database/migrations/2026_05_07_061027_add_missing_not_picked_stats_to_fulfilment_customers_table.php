<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table): void {
            if (!Schema::hasColumn('fulfilment_customers', 'number_pallets_state_not_picked')) {
                $table->unsignedInteger('number_pallets_state_not_picked')->default(0);
            }

            if (!Schema::hasColumn('fulfilment_customers', 'number_pallets_with_stored_items_state_not_picked')) {
                $table->unsignedInteger('number_pallets_with_stored_items_state_not_picked')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table): void {
            $columnsToDrop = [];

            if (Schema::hasColumn('fulfilment_customers', 'number_pallets_state_not_picked')) {
                $columnsToDrop[] = 'number_pallets_state_not_picked';
            }

            if (Schema::hasColumn('fulfilment_customers', 'number_pallets_with_stored_items_state_not_picked')) {
                $columnsToDrop[] = 'number_pallets_with_stored_items_state_not_picked';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
