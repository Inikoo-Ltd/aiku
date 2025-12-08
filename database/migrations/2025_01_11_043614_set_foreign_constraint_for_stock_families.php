<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Jan 2025 12:36:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->delete_stock_families_foreign_constraint();

        Schema::table('stock_family_stats', function (Blueprint $table) {
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->cascadeOnDelete();
        });

        Schema::table('stock_family_intervals', function (Blueprint $table) {
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->cascadeOnDelete();
        });

        Schema::table('stock_family_time_series', function (Blueprint $table) {
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->cascadeOnDelete();
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->nullOnDelete();
        });

        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->nullOnDelete();
        });

        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnDelete();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->nullOnDelete();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnDelete();
        });

        Schema::table('org_stock_families', function (Blueprint $table) {
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->cascadeOnDelete();
        });

        Schema::table('org_stock_family_stats', function (Blueprint $table) {
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->cascadeOnDelete();
        });

        Schema::table('org_stock_family_time_series', function (Blueprint $table) {
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->cascadeOnDelete();
        });

        Schema::table('org_stock_family_intervals', function (Blueprint $table) {
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->cascadeOnDelete();
        });

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnDelete();
        });
    }

    public function down(): void
    {
        //
    }

    private function delete_stock_families_foreign_constraint(): void
    {
        Schema::table('stock_family_stats', function (Blueprint $table) {
            $table->dropForeign(['stock_family_id']);
        });
        Schema::table('stock_family_intervals', function (Blueprint $table) {
            $table->dropForeign(['stock_family_id']);
        });
        Schema::table('stock_family_time_series', function (Blueprint $table) {
            $table->dropForeign(['stock_family_id']);
        });
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['stock_family_id']);
        });

        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropForeign(['stock_family_id']);
        });

        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropForeign(['org_stock_family_id']);
        });

        Schema::table('org_stock_families', function (Blueprint $table) {
            $table->dropForeign(['stock_family_id']);
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropForeign(['stock_family_id']);
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropForeign(['org_stock_family_id']);
        });

        Schema::table('org_stock_family_stats', function (Blueprint $table) {
            $table->dropForeign(['org_stock_family_id']);
        });

        Schema::table('org_stock_family_time_series', function (Blueprint $table) {
            $table->dropForeign(['org_stock_family_id']);
        });

        Schema::table('org_stock_family_intervals', function (Blueprint $table) {
            $table->dropForeign(['org_stock_family_id']);
        });

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropForeign(['org_stock_family_id']);
        });
    }
};
