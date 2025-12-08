<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Nov 2024 01:43:24 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::create('outbox_intervals', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('outbox_id')->nullable();
            $table->foreign('outbox_id')->references('id')->on('outboxes');

            $fields = [
                'runs', // number of MailShot|EmailBulkRun|EmailPush
                'dispatched_emails',
                'opened_emails',
                'clicked_emails',
                'bounced_emails',
                'subscribed',
                'unsubscribed',
            ];

            $table = $this->unsignedIntegerDateIntervals($table, $fields);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_intervals');
    }
};
