<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Transfers\FetchStack\FetchStackStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('fetch_stacks', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->string('operation')->index();
            $table->unsignedBigInteger('operation_id');
            $table->string('state')->default(FetchStackStateEnum::IN_PROCESS->value)->index();
            $table->jsonb('result');
            $table->jsonb('errors');
            $table->dateTimeTz('submitted_at')->index();
            $table->dateTimeTz('send_to_queue_at')->nullable()->index();
            $table->dateTimeTz('start_fetch_at')->nullable()->index();
            $table->dateTimeTz('finish_fetch_at')->nullable()->nullable()->index();
            $table->dateTimeTz('error_at')->nullable()->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('fetch_stacks');
    }
};
