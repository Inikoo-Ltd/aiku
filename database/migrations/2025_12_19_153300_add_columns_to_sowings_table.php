<?php

/*
 *  Author: Oggie Sutrisna
 *  Created: Thu, 19 Dec 2024 Malaysia Time
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sowings', function (Blueprint $table) {
            if (!Schema::hasColumn('sowings', 'stock_delivery_id')) {
                $table->unsignedInteger('stock_delivery_id')->nullable()->index()->after('return_item_id');
                $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries')->nullOnDelete();
            }

            if (!Schema::hasColumn('sowings', 'stock_delivery_item_id')) {
                $table->unsignedInteger('stock_delivery_item_id')->nullable()->index()->after('stock_delivery_id');
                $table->foreign('stock_delivery_item_id')->references('id')->on('stock_delivery_items')->nullOnDelete();
            }

            if (!Schema::hasColumn('sowings', 'return_id')) {
                $table->unsignedInteger('return_id')->nullable()->index()->after('org_stock_movement_id');
                $table->foreign('return_id')->references('id')->on('returns')->nullOnDelete();
            }

            if (!Schema::hasColumn('sowings', 'return_item_id')) {
                $table->unsignedInteger('return_item_id')->nullable()->index()->after('return_id');
                $table->foreign('return_item_id')->references('id')->on('return_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sowings', function (Blueprint $table) {
            $table->dropForeign(['stock_delivery_id']);
            $table->dropColumn('stock_delivery_id');

            $table->dropForeign(['stock_delivery_item_id']);
            $table->dropColumn('stock_delivery_item_id');

            $table->dropForeign(['return_id']);
            $table->dropColumn('return_id');

            $table->dropForeign(['return_item_id']);
            $table->dropColumn('return_item_id');
        });
    }
};
