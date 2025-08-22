<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Aug 2025 16:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\MasterCollection\MasterCollectionProductStatusEnum;
use App\Enums\Catalogue\MasterCollection\MasterCollectionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->unsignedInteger('image_id')->nullable();
            $table->string('state')->default(MasterCollectionStateEnum::IN_PROCESS->value)->index();
            $table->string('products_status')->default(MasterCollectionProductStatusEnum::NORMAL->value)->index();
        });
    }

    public function down(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->dropColumn(['state', 'products_status', 'image_id']);
            $table->boolean('status')->default(true)->index();
        });
    }
};
