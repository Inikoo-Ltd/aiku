<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 13:22:29 Central Indonesia Time, Sanur, Bali, Indonesia
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
            $table->decimal('running_quantity', 10)->nullable()->after('quantity');
            $table->decimal('running_in_pallet_quantity', 10)->nullable()->after('running_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stored_item_movements', function (Blueprint $table) {
            $table->dropColumn('running_quantity');
            $table->dropColumn('running_in_pallet_quantity');
        });
    }
};
