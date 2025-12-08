<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Mar 2025 23:21:14 Malaysia Time, Kuala Lumpur, Malaysia
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
            $table->text('collection_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropColumn('collection_notes');
        });
    }
};
