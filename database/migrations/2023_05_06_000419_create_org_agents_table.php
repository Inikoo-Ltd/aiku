<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('org_agents', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->unsignedSmallInteger('agent_id');
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->boolean('status')->default(true)->index();
            $table->timestampsTz();
            $table->string('source_id')->index()->nullable();
            $table->unique(['group_id', 'organisation_id', 'agent_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_agents');
    }
};
