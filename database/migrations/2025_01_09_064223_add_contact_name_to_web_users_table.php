<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jan 2025 15:22:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('web_users', function (Blueprint $table) {
            $table->string('contact_name')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('web_users', function (Blueprint $table) {
            $table->dropColumn('contact_name');
        });
    }
};
