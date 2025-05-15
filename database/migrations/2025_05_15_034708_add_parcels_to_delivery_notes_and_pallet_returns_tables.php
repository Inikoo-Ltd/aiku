<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->jsonb('parcels')->nullable();
        });

        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->jsonb('parcels')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('parcels');
        });

        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropColumn('parcels');
        });
    }
};
