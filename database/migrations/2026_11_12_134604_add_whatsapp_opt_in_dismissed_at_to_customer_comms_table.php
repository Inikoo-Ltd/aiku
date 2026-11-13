<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $table->dateTimeTz('whatsapp_newsletter_opt_in_dismissed_at')->nullable()->comment('the customer chose Not interested on the dashboard WhatsApp card; they can still opt in from their settings');
        });
    }

    public function down(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $table->dropColumn('whatsapp_newsletter_opt_in_dismissed_at');
        });
    }
};
