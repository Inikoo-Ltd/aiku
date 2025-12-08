<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 15 May 2023 16:08:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Inventory\OrgStock\LostAndFoundOrgStockStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_and_found_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('location_id')->index();
            $table->foreign('location_id')->references('id')->on('locations');
            $table->string('code');
            $table->decimal('quantity', 16, 3)->default(0);
            $table->decimal('stock_value', 16)->default(0);
            $table->string('type')->index()->default(LostAndFoundOrgStockStateEnum::LOST->value);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_and_found_stocks');
    }
};
