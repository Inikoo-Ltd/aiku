<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Feb 2025 10:26:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->decimal('total_quantity')->default(0)->comment('Total stock of the item in the warehouse');
            $table->unsignedInteger('number_pallets')->nullable()->index();
            $table->unsignedInteger('number_audits')->default(0);
            $table->dateTimeTz('last_audit_at')->nullable();
            $table->unsignedInteger('last_stored_item_audit_delta_id')->nullable()->index();
            $table->foreign('last_stored_item_audit_delta_id')->references('id')->on('stored_item_audit_deltas')->nullOnDelete();
            $table->unsignedInteger('last_stored_item_audit_id')->nullable()->index();
            $table->foreign('last_stored_item_audit_id')->references('id')->on('stored_item_audits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->dropColumn('total_quantity');
        });
    }
};
