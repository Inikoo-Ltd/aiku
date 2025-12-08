<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 13:11:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shipping_zone_schema_id')) {
                $table->unsignedSmallInteger('shipping_zone_schema_id')->nullable()->index();
                $table->foreign('shipping_zone_schema_id')->references('id')->on('shipping_zone_schemas');
            }
            if (! Schema::hasColumn('orders', 'shipping_zone_id')) {
                $table->unsignedSmallInteger('shipping_zone_id')->nullable()->index();
                $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones');
            }
        });

        // Invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'shipping_zone_schema_id')) {
                $table->unsignedSmallInteger('shipping_zone_schema_id')->nullable()->index();
                $table->foreign('shipping_zone_schema_id')->references('id')->on('shipping_zone_schemas');
            }
            if (! Schema::hasColumn('invoices', 'shipping_zone_id')) {
                $table->unsignedSmallInteger('shipping_zone_id')->nullable()->index();
                $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones');
            }
        });

        // Delivery Notes
        Schema::table('delivery_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_notes', 'shipping_zone_schema_id')) {
                $table->unsignedSmallInteger('shipping_zone_schema_id')->nullable()->index();
                $table->foreign('shipping_zone_schema_id')->references('id')->on('shipping_zone_schemas');
            }
            if (! Schema::hasColumn('delivery_notes', 'shipping_zone_id')) {
                $table->unsignedSmallInteger('shipping_zone_id')->nullable()->index();
                $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones');
            }
        });

        // Pallet Returns
        Schema::table('pallet_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('pallet_returns', 'shipping_zone_schema_id')) {
                $table->unsignedSmallInteger('shipping_zone_schema_id')->nullable()->index();
                $table->foreign('shipping_zone_schema_id')->references('id')->on('shipping_zone_schemas');
            }
            if (! Schema::hasColumn('pallet_returns', 'shipping_zone_id')) {
                $table->unsignedSmallInteger('shipping_zone_id')->nullable()->index();
                $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones');
            }
        });
    }

    public function down(): void
    {
        // Orders
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipping_zone_id')) {
                $table->dropForeign(['shipping_zone_id']);
                $table->dropColumn('shipping_zone_id');
            }
            if (Schema::hasColumn('orders', 'shipping_zone_schema_id')) {
                $table->dropForeign(['shipping_zone_schema_id']);
                $table->dropColumn('shipping_zone_schema_id');
            }
        });

        // Invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'shipping_zone_id')) {
                $table->dropForeign(['shipping_zone_id']);
                $table->dropColumn('shipping_zone_id');
            }
            if (Schema::hasColumn('invoices', 'shipping_zone_schema_id')) {
                $table->dropForeign(['shipping_zone_schema_id']);
                $table->dropColumn('shipping_zone_schema_id');
            }
        });

        // Delivery Notes
        Schema::table('delivery_notes', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_notes', 'shipping_zone_id')) {
                $table->dropForeign(['shipping_zone_id']);
                $table->dropColumn('shipping_zone_id');
            }
            if (Schema::hasColumn('delivery_notes', 'shipping_zone_schema_id')) {
                $table->dropForeign(['shipping_zone_schema_id']);
                $table->dropColumn('shipping_zone_schema_id');
            }
        });

        // Pallet Returns
        Schema::table('pallet_returns', function (Blueprint $table) {
            if (Schema::hasColumn('pallet_returns', 'shipping_zone_id')) {
                $table->dropForeign(['shipping_zone_id']);
                $table->dropColumn('shipping_zone_id');
            }
            if (Schema::hasColumn('pallet_returns', 'shipping_zone_schema_id')) {
                $table->dropForeign(['shipping_zone_schema_id']);
                $table->dropColumn('shipping_zone_schema_id');
            }
        });
    }
};
