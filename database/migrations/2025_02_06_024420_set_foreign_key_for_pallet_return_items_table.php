<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Feb 2025 11:27:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->dropForeign(['pallet_stored_item_id']);
            $table->foreign('pallet_stored_item_id')->references('id')->on('pallet_stored_items')->nullOnDelete();
        });
    }

    public function down(): void {}
};
