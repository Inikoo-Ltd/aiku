<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Nov 2024 21:00:21 Central Indonesia Time, Kuala Lumpur, Malaysia
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
        Schema::create('org_post_room_intervals', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('org_post_room_id')->nullable();
            $table->foreign('org_post_room_id')->references('id')->on('org_post_rooms');

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
        Schema::dropIfExists('v');
    }
};
