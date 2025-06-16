<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 May 2025 20:51:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\Packing\PackingStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('packings', function (Blueprint $table) {
            $table->renameColumn('quantity_packed', 'quantity');
            $table->renameColumn('packer_id', 'packer_user_id');

            $table->dropForeign(['picking_id']);

            $table->dropColumn('picking_id');
            $table->dropColumn('state');
        });
    }


    public function down(): void
    {
        Schema::table('packings', function (Blueprint $table) {
            $table->renameColumn('quantity', 'quantity_packed');
            $table->renameColumn('packer_user_id', 'packer_id');
            $table->unsignedBigInteger('picking_id')->nullable();
            $table->foreign('picking_id')->references('id')->on('pickings');
            $table->string('state')->default(PackingStateEnum::QUEUED->value)->index();
        });
    }
};
