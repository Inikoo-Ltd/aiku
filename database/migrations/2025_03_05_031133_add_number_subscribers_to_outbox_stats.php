<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 11:11:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_subscribed_user')->default(0);
            $table->unsignedSmallInteger('number_subscribed_external_emails')->default(0);

        });
    }

    public function down(): void
    {
        Schema::table('outbox_stats', function (Blueprint $table) {
            $table->dropColumn('number_subscribed_user');
            $table->dropColumn('number_subscribed_external_emails');
        });
    }
};
