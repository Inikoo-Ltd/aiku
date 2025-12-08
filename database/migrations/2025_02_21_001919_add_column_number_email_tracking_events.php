<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Feb 2025 13:12:38 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('number_email_tracking_events')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropColumn('number_email_tracking_events');
        });
    }
};
