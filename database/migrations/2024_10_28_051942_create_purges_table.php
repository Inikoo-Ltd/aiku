<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Nov 2024 11:19:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('purges', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('state')->default(PurgeStateEnum::IN_PROCESS);
            $table->string('type');
            $table->dateTimeTz('scheduled_at');
            $table->dateTimeTz('start_at');
            $table->dateTimeTz('end_at');
            $table->dateTimeTz('cancelled_at');
            $table->unsignedSmallInteger('inactive_days');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('purges');
    }
};
