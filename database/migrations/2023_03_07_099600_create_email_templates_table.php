<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 12:27:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name')->index()->collation('und_ns');
            $table->string('state')->index()->default(EmailTemplateStateEnum::IN_PROCESS);
            $table->boolean('is_seeded')->index()->default(false);
            $table->string('builder')->index();
            $table->jsonb('layout');
            $table->json('arguments');
            $table->json('data');

            $table->unsignedInteger('screenshot_id')->nullable();
            $table->foreign('screenshot_id')->references('id')->on('media');
            $table->unsignedSmallInteger('language_id')->default(68);
            $table->foreign('language_id')->references('id')->on('languages');

            $table->dateTimeTz('active_at')->nullable();
            $table->dateTimeTz('suspended_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
