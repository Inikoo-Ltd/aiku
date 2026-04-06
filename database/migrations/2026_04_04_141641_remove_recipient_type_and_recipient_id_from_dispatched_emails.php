<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Apr 2026 22:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropIndex(['recipient_type', 'recipient_id']);
            $table->dropColumn(['recipient_type', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->string('recipient_type')->nullable();
            $table->unsignedInteger('recipient_id')->nullable();
            $table->index(['recipient_type', 'recipient_id']);
        });
    }
};
