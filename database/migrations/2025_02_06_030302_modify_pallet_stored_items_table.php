<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Feb 2025 11:27:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->boolean('in_process')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->dropColumn('in_process');
        });
    }
};
