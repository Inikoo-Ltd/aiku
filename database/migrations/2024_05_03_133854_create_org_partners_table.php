<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 14:47:56 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
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
        Schema::create('org_partners', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('partner_id');
            $table->foreign('partner_id')->references('id')->on('organisations');
            $table->boolean('status')->default(true)->index();
            $table->timestampsTz();
            $table->jsonb('sources');
            $table->unique(['group_id', 'organisation_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_partners');
    }
};
