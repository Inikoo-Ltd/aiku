<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 14:05:44 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasWebStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasWebStats;

    public function up(): void
    {
        Schema::create('webpage_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('webpage_id');
            $table->foreign('webpage_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->getContentBlocksFields($table);
            $table = $this->getSnapshotsFields($table);
            $table = $this->getDeploymentsFields($table);
            $table = $this->getChildWebpagesStatsFields($table);
            $table = $this->getBannersStatsFields($table);
            $table = $this->getRedirectsStatsFields($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webpage_stats');
    }
};
