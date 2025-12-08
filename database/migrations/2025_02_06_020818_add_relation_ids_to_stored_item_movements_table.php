<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Feb 2025 10:23:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_item_movements', function (Blueprint $table) {
            $table->unsignedInteger('pallet_id')->nullable()->index();
            $table->foreign('pallet_id')->references('id')->on('pallets')->onDelete('set null');
            $table->unsignedInteger('stored_item_audit_id')->nullable()->index();
            $table->foreign('stored_item_audit_id')->references('id')->on('stored_item_audits')->onDelete('set null');
            $table->unsignedInteger('stored_item_audit_delta_id')->nullable()->index();
            $table->foreign('stored_item_audit_delta_id')->references('id')->on('stored_item_audit_deltas')->onDelete('set null');
            $table->unsignedInteger('pallet_delivery_id')->nullable()->index();
            $table->foreign('pallet_delivery_id')->references('id')->on('pallet_deliveries')->onDelete('set null');
            $table->unsignedInteger('pallet_return_id')->nullable()->index();
            $table->foreign('pallet_return_id')->references('id')->on('pallet_returns')->onDelete('set null');
            $table->unsignedInteger('pallet_return_item_id')->nullable()->index();
            $table->foreign('pallet_return_item_id')->references('id')->on('pallet_return_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('stored_item_movements', function (Blueprint $table) {
            $table->dropForeign(['stored_item_audit_id']);
            $table->dropForeign(['stored_item_audit_delta_id']);
            $table->dropForeign(['pallet_delivery_id']);
            $table->dropForeign(['pallet_id']);
            $table->dropForeign(['pallet_return_id']);
            $table->dropForeign(['pallet_return_item_id']);
        });
    }
};
