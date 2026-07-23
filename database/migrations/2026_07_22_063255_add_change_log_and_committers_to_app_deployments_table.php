<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 14:32:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('app_deployments', function (Blueprint $table) {
            $table->text('change_log')->nullable();
            $table->jsonb('committers')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('app_deployments', function (Blueprint $table) {
            $table->dropColumn(['change_log', 'committers']);
        });
    }
};
