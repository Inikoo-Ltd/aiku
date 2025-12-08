<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jan 2025 12:34:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->boolean('is_collection')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropColumn('is_collection');
        });
    }
};
