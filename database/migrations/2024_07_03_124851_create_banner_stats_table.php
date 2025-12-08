<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Aug 2023 16:11:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('banner_id');
            $table->foreign('banner_id')->references('id')->on('banners')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('number_snapshots')->default(0);
            foreach (SnapshotStateEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_snapshots_state_'.Str::replace('-', '_', $state->snake()))->default(0);
            }
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_stats');
    }
};
