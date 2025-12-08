<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 18:04:32 Central Indonesia Time, Sanur, Bali, Indonesia
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
            $table->unsignedSmallInteger('platform_id')->index()->nullable();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropColumn(['platform_id']);
            $table->dropForeign(['platform_id']);
        });
    }
};
