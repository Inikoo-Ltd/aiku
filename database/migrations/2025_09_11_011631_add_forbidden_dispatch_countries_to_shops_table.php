<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 09:39:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->jsonb('forbidden_dispatch_countries')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['forbidden_dispatch_countries']);
        });
    }
};
