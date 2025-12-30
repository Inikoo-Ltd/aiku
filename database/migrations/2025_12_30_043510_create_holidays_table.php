<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 13:02:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->timestampsTz();
            $table->string('type');
            $table->unsignedSmallInteger('year')->index();
            $table->string('label')->nullable()->index();
            $table->date('from')->index();
            $table->date('to')->index();
            $table->index(['from', 'to']);
            $table->json('data')->default('{}');

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
