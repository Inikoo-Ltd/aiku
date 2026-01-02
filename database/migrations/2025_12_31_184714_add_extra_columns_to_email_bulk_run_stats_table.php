<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 02:48:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_bulk_run_stats', function (Blueprint $table) {
            $table->unsignedMediumInteger('number_error_emails')->default(0);
            $table->unsignedMediumInteger('number_rejected_emails')->default(0);
            $table->unsignedMediumInteger('number_sent_emails')->default(0);
            $table->unsignedMediumInteger('number_delivered_emails')->default(0);
            $table->unsignedMediumInteger('number_hard_bounced_emails')->default(0);
            $table->unsignedMediumInteger('number_soft_bounced_emails')->default(0);
            $table->unsignedMediumInteger('number_opened_emails')->default(0);
            $table->unsignedMediumInteger('number_clicked_emails')->default(0);
            $table->unsignedMediumInteger('number_spam_emails')->default(0);
            $table->unsignedMediumInteger('number_unsubscribed_emails')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('email_bulk_run_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_error_emails',
                'number_rejected_emails',
                'number_sent_emails',
                'number_delivered_emails',
                'number_hard_bounced_emails',
                'number_soft_bounced_emails',
                'number_opened_emails',
                'number_clicked_emails',
                'number_spam_emails',
                'number_unsubscribed_emails',
            ]);
        });
    }
};
