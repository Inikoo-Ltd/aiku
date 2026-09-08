<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_addresses', function (Blueprint $table) {
            $table->dropColumn([
                'last_marketing_dispatch_at',
                'last_transactional_dispatch_at',
                'soft_bounced_at',
                'hard_bounced_at',
                'number_marketing_dispatches',
                'number_transactional_dispatches',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('email_addresses', function (Blueprint $table) {
            $table->dateTimeTz('last_marketing_dispatch_at')->nullable()->index();
            $table->dateTimeTz('last_transactional_dispatch_at')->nullable()->index();
            $table->dateTimeTz('soft_bounced_at')->nullable()->index();
            $table->dateTimeTz('hard_bounced_at')->nullable()->index();
            $table->unsignedSmallInteger('number_marketing_dispatches')->default(0);
            $table->unsignedSmallInteger('number_transactional_dispatches')->default(0);
        });
    }
};
