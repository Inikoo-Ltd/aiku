<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:22:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCRMStats;
use App\Stubs\Migrations\HasProspectStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasCRMStats;
    use HasProspectStats;
    public function up(): void
    {
        Schema::create('organisation_crm_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->customerStats($table);
            $table = $this->prospectsStats($table);
            $table->unsignedSmallInteger('number_prospect_queries')->default(0);
            $table->unsignedSmallInteger('number_customer_queries')->default(0);
            $table->unsignedSmallInteger('number_surveys')->default(0);
            $table = $this->getWebUsersStatsFields($table);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('organisation_crm_stats');
    }
};
