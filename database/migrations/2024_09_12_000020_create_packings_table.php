<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Dec 2024 14:55:40 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\Packing\PackingEngineEnum;
use App\Enums\Dispatching\Packing\PackingStateEnum;
use App\Stubs\Migrations\HasPicking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasPicking;

    public function up(): void
    {
        Schema::create('packings', function (Blueprint $table) {
            $table = $this->pickingHead($table);

            $table->unsignedBigInteger('picking_id')->index()->nullable();
            $table->foreign('picking_id')->references('id')->on('pickings');

            $table->string('state')->default(PackingStateEnum::QUEUED->value)->index();
            $table->decimal('quantity_packed', 16, 3)->nullable();

            $table->unsignedSmallInteger('packer_id')->nullable()->index();
            $table->foreign('packer_id')->references('id')->on('users');

            $table->string('engine')->defaukt(PackingEngineEnum::AIKU->value)->index();

            $table->jsonb('data');

            $table->dateTimeTz('queued_at')->nullable();
            $table->dateTimeTz('packing_at')->nullable();
            $table->dateTimeTz('packing_blocked_at')->nullable();
            $table->dateTimeTz('done_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packings');
    }
};
