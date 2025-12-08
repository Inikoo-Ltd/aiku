<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Mar 2023 16:50:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
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
        Schema::create('post_room_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('post_room_id')->nullable();
            $table->foreign('post_room_id')->references('id')->on('post_rooms');
            $table = $this->orgPostRoomsStats($table);
            $table = $this->outboxesStats($table);
            $table = $this->mailshotsStats($table);
            $table = $this->dispatchedEmailStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_room_stats');
    }
};
