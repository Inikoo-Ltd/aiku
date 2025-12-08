<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Production\Production\ProductionStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index()->collation('und_ns');
            $table->string('name');
            $table->string('state')->index()->default(ProductionStateEnum::IN_PROCESS->value);
            $table->jsonb('settings');
            $table->jsonb('data');
            $table->datetimeTz('opened_at')->nullable();
            $table->datetimeTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->jsonb('sources');
            $table->unique(['group_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
