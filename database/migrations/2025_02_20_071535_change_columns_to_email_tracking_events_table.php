<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Feb 2025 17:19:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->string('ip')->nullable();
            $table->string('device')->nullable();
            $table->dropColumn(['provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->dropColumn(['ip']);
            $table->dropColumn(['device']);
            $table->string('provider_reference')->nullable();
        });
    }
};
