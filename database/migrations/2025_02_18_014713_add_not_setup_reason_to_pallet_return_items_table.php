<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Feb 2025 12:22:05 Central Indonesia Time, Sanur, Bali, Indonesia
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
            $table->string('not_setup_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->dropColumn(['not_setup_reason']);
        });
    }
};
