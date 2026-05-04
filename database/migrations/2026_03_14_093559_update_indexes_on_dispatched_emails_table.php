<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 17:32:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropIndex('dispatched_emails_provider_provider_dispatch_id_index');
            $table->dropIndex('dispatched_emails_provider_index');
            $table->index('provider_dispatch_id');
        });
    }


    public function down(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropIndex(['provider_dispatch_id']);
            $table->index('provider');
            $table->index(['provider', 'provider_dispatch_id']);
        });
    }
};
