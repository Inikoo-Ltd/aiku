<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('organisation_stock_histories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->date('date')->index();
            $table->decimal('org_stock_value', 16)->comment('FIFO method')->default(0);
            $table->decimal('grp_stock_value', 16)->comment('FIFO method')->default(0);
            $table->decimal('org_stock_commercial_value', 16)->default(0);
            $table->decimal('grp_stock_commercial_value', 16)->default(0);
            $table->unsignedInteger('number_org_stocks')->default(0);
            $table->unsignedInteger('number_out_of_stock_org_stocks')->default(0);
            $table->unsignedInteger('number_location_org_stocks')->default(0);
            $table->timestampsTz();
            $table->unique(['organisation_id', 'date']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('organisation_stock_histories');
    }
};
