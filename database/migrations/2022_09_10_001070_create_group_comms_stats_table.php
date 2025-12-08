<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 20:25:31 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCommsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCommsStats;

    public function up(): void
    {
        Schema::create('group_comms_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->postRoomsStats($table);
            $table = $this->orgPostRoomsStats($table);
            $table = $this->outboxesStats($table);
            $table = $this->mailshotsStats($table);
            $table = $this->bulkRunsStats($table);
            $table = $this->dispatchedEmailStats($table);
            $table = $this->emailAddressStatsFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_comms_stats');
    }
};
