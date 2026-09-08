<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sep 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('aiku_public_visits', function (Blueprint $table) {
            $table->boolean('is_bot')->default(false)->index();
            $table->string('user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aiku_public_visits', function (Blueprint $table) {
            $table->dropColumn(['is_bot', 'user_agent']);
        });
    }
};
