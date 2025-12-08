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
        Schema::create('email_ongoing_run_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('email_ongoing_run_id')->nullable();
            $table->foreign('email_ongoing_run_id')->references('id')->on('email_ongoing_runs');
            $fields = [
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
        Schema::dropIfExists('email_ongoing_run_intervals');
    }
};
