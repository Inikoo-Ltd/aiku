<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 13:54:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('web_users', function (Blueprint $table) {
            $table->string('google_id')->index()->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('web_users', function (Blueprint $table) {
            $table->dropColumn(['google_id']);
        });
    }
};
