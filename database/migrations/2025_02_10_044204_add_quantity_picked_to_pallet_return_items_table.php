<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Feb 2025 12:44:20 Central Indonesia Time, Kuala Lumpur, Malaysia
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
            $table->decimal('quantity_picked', 16, 3)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->dropColumn('quantity_picked');
        });
    }
};
