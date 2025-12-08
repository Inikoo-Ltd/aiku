<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Jan 2025 16:03:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_bill_transactions', function (Blueprint $table) {
            $table->unsignedInteger('pallet_delivery_id')->index()->nullable();
            $table->foreign('pallet_delivery_id')->references('id')->on('pallet_deliveries')->cascadeOnDelete();
            $table->unsignedInteger('pallet_return_id')->index()->nullable();
            $table->foreign('pallet_return_id')->references('id')->on('pallet_returns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_bill_transactions', function (Blueprint $table) {
            $table->dropColumn('pallet_delivery_id');
            $table->dropColumn('pallet_return_id');
        });
    }
};
