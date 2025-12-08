<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jul 2023 12:56:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->increments('id');
            $table->ulid()->index()->nullable();
            $table->unsignedInteger('snapshot_id');
            $table->boolean('visibility')->default(true)->index();
            $table->jsonb('layout')->nullable();
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media');
            $table->unsignedInteger('mobile_image_id')->nullable();
            $table->foreign('mobile_image_id')->references('id')->on('media');
            $table->unsignedInteger('tablet_image_id')->nullable();
            $table->foreign('tablet_image_id')->references('id')->on('media');
            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides');
    }
};
