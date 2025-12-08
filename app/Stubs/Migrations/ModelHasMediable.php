<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Jun 2024 18:31:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait ModelHasMediable
{
    public function modelMediaFields(Blueprint $table): Blueprint
    {

        $table->string('scope')->nullable()->index();
        $table->string('sub_scope')->nullable()->index();
        $table->jsonb('data');
        $table->text('caption')->nullable();
        $table->unsignedInteger('media_id');
        $table->foreign('media_id')->references('id')->on('media');
        $table->string('model_type');
        $table->unsignedInteger('model_id');
        $table->timestampsTz();
        $table->datetimeTz('fetched_at')->nullable();
        $table->datetimeTz('last_fetched_at')->nullable();
        $table->index(['model_type', 'model_id']);
        $table->string('source_id')->nullable()->unique();
        $table->unique(['media_id', 'model_type', 'model_id', 'scope', 'sub_scope']);

        return $table;
    }
}
