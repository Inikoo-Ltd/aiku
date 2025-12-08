<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:22:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasHelpersStats;
use App\Stubs\Migrations\HasQueriesStats;
use App\Stubs\Migrations\HasSysAdminStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasHelpersStats;
    use HasQueriesStats;
    use HasSysAdminStats;

    public function up(): void
    {
        Schema::create('organisation_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');

            $table->boolean('has_fulfilment')->default('false');
            $table->boolean('has_dropshipping')->default('false');
            $table->boolean('has_production')->default('false');
            $table->boolean('has_agents')->default('false');

            $table = $this->imagesStats($table);
            $table = $this->attachmentsStats($table);
            $table = $this->uploadStats($table);
            $table = $this->getQueriesStats($table);
            $table = $this->auditFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_stats');
    }
};
