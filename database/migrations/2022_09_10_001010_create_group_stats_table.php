<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 19:25:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Stubs\Migrations\HasHelpersStats;
use App\Stubs\Migrations\HasQueriesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasHelpersStats;
    use HasQueriesStats;

    public function up(): void
    {
        Schema::create('group_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('number_organisations')->default(0);
            foreach (OrganisationTypeEnum::cases() as $case) {
                $table->unsignedInteger('number_organisations_type_'.$case->snake())->default(0);
            }

            $table = $this->imagesStats($table);
            $table = $this->attachmentsStats($table);
            $table = $this->uploadStats($table);
            $table = $this->getQueriesStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_stats');
    }
};
