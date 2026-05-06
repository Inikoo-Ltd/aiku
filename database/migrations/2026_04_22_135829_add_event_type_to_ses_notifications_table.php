<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Apr 2026 22:01:09 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ses_notifications', function (Blueprint $table) {
            $table->string('event_type')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ses_notifications', function (Blueprint $table) {
            $table->dropColumn('event_type');
        });
    }
};
