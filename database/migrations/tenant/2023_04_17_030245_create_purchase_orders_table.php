<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 13:31:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->unique();
            $table->unsignedInteger('provider_id')->index();
            $table->string('provider_type');
            $table->string('number');
            $table->jsonb('data');
            $table->string('state')->index()->default(PurchaseOrderStateEnum::CREATING->value);
            $table->string('status')->index()->default(PurchaseOrderStatusEnum::PROCESSING->value);
            $table->dateTimeTz('date')->comment('latest relevant date');


            $table->dateTimeTz('submitted_at')->nullable();
            $table->dateTimeTz('confirmed_at')->nullable();
            $table->dateTimeTz('manufactured_at')->nullable();
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('checked_at')->nullable();
            $table->dateTimeTz('settled_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unsignedInteger('source_id')->nullable()->unique();


            $table->index(['provider_id', 'provider_type']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
