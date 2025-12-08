<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jan 2025 15:24:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_item_audit_deltas', function (Blueprint $table) {
            $table->renameColumn('reason', 'notes');
            $table->unsignedSmallInteger('location_id')->index()->nullable();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
            $table->boolean('is_new_stored_item')->default(false)->comment('Stored item just created');
            $table->boolean('is_stored_item_new_in_pallet')->default(false)->comment('Existing Stored item was associated to the pallet');

        });

    }

    public function down(): void
    {
        Schema::table('stored_item_audit_deltas', function (Blueprint $table) {
            $table->renameColumn('notes', 'reason');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
            $table->dropColumn('is_new_stored_item');
            $table->dropColumn('is_stored_item_new_in_pallet');
        });
    }
};
