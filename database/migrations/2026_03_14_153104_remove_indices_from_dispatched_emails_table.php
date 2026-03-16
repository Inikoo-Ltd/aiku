<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 15:31:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropIndex('dispatched_emails_number_reads_index');
            $table->dropIndex('dispatched_emails_number_email_tracking_events_index');
            $table->dropIndex('dispatched_emails_number_clicks_index');
            $table->dropIndex('dispatched_emails_mask_as_spam_index');
        });
    }


    public function down(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->index('number_reads');
            $table->index('number_email_tracking_events');
            $table->index('number_clicks');
            $table->index('mask_as_spam');
        });
    }
};
